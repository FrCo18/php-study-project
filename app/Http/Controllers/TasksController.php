<?php

namespace App\Http\Controllers;

use App\Courses\TaskChecker;
use App\Http\Traits\NotAllowedFunctionsTrait;
use App\Models\ProgressCourse;
use App\Models\ProgressTask;
use App\Models\Tasks;
use App\Models\User;
use Exception;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\JsonResponse;

class TasksController extends Controller
{
    use NotAllowedFunctionsTrait;

    public function getTasksByCourseId(Request $request): JsonResponse
    {
        $request_params = [
            'id_course' => $request->get('id_course'),
            'id_user' => $request->get('id_user')
        ];

        $check_params = self::checkExistsParams($request_params);

        if ($check_params instanceof JsonResponse) {
            return $check_params;
        }

        $request_params['id_user'] = User::decryptUserId($request_params['id_user']);

        if ($request_params['id_user'] instanceof JsonResponse) {
            return $request_params['id_user'];
        }

        $tasks = Tasks::getTasksByCourseId($request_params['id_course'], $request_params['id_user']);
        return response()->json($tasks);
    }

    public function getTaskById(Request $request): JsonResponse
    {
        $request_params = [
            'id_task' => $request->get('id_task'),
            'id_user' => $request->get('id_user')
        ];

        $check_params = self::checkExistsParams($request_params);

        if ($check_params instanceof JsonResponse) {
            return $check_params;
        }

        $request_params['id_user'] = User::decryptUserId($request_params['id_user']);

        if ($request_params['id_user'] instanceof JsonResponse) {
            return $request_params['id_user'];
        }

        $task = Tasks::getTaskById($request_params['id_task'], $request_params['id_user']);
        $task['is_complete'] = $task['is_complete'] ?: false;
        return response()->json($task);
    }

    public function compileCode(Request $request): array|JsonResponse|\Illuminate\Http\JsonResponse
    {
        $request_params = [
            'php_code' => $request->get('php_code'),
        ];

        $check_params = self::checkExistsParams($request_params);

        if ($check_params instanceof JsonResponse) {
            return $check_params;
        }

        $_SESSION['echo_system_var'] = '';

        $arr_replaces = [
            '/^\s*<\?php|\?>\s*$/' => '',
            '/\$_SESSION\[[\'"]echo_system_var[\'"]]/' => '',
            '/echo/' => '$_SESSION[\'echo_system_var\'] .='
        ];

        $php_code = preg_replace(array_keys($arr_replaces), $arr_replaces, $request_params['php_code']);

        try {
            foreach ($this->not_allowed_functions as $not_allowed_function) {
                if (preg_match('/' . $not_allowed_function . '\s*\(/', $php_code)) {
                    return [
                        'is_complete' => false,
                        'eval_result' => '',
                        'error_text' => 'It is function is not allowed!',
                        'echo_text' => $_SESSION['echo_system_var']
                    ];
                }
            }

            eval($php_code);

            return [
                'error_text' => '',
                'echo_text' => $_SESSION['echo_system_var']
            ];
        } catch (\ParseError|\ErrorException|\Error $e) {
            return [
                'error_text' => $e->getMessage(),
                'echo_text' => $_SESSION['echo_system_var']
            ];
        }
    }

    /**
     * @throws Exception
     */
    public function checkTask(Request $request): JsonResponse|\Illuminate\Http\JsonResponse|array
    {
        $request_params = [
            'php_code' => $request->get('php_code'),
            'course_name' => $request->get('course_name'),
            'level_number' => $request->get('level_number'),
            'id_user' => $request->get('id_user'),
            'id_task' => $request->get('id_task'),
            'id_course' => $request->get('id_course'),
        ];

        $check_params = self::checkExistsParams($request_params);

        if ($check_params instanceof JsonResponse) {
            return $check_params;
        }

        $request_params['id_user'] = User::decryptUserId($request_params['id_user']);

        if ($request_params['id_user'] instanceof JsonResponse) {
            return $request_params['id_user'];
        }

        $_SESSION['echo_system_var'] = '';

        $arr_replaces = [
            '/^\s*<\?php|\?>\s*$/' => '',
            '/\$_SESSION\[[\'"]echo_system_var[\'"]]/' => '',
            '/echo/' => '$_SESSION[\'echo_system_var\'] .='
        ];

        $php_code = preg_replace(array_keys($arr_replaces), $arr_replaces, $request_params['php_code']);

        try {

            foreach ($this->not_allowed_functions as $not_allowed_function) {
                if (preg_match('/' . $not_allowed_function . '\s*\(/', $php_code)) {
                    return [
                        'is_complete' => false,
                        'eval_result' => '',
                        'error_text' => 'It is function is not allowed!',
                        'echo_text' => $_SESSION['echo_system_var']
                    ];
                }
            }

            $eval_result = eval($php_code) ?: '';

            $task_checker = new TaskChecker($request_params['course_name'], $request_params['level_number']);
            $is_complete = $task_checker->checkTask($request_params['php_code'], $eval_result, $_SESSION['echo_system_var']);

            $result = ProgressTask::setProgressTask($request_params['id_task'], $request_params['id_user'], $is_complete);
            if (!$result) {
                throw new Exception('Fail set to ProgressTasks', 500);
            }

            $is_complete_all_tasks = ProgressTask::isCompleteAllTasks($request_params['id_course'], $request_params['id_user']);
            $result = ProgressCourse::setProgressCourse($request_params['id_course'], $request_params['id_user'], $is_complete_all_tasks);
            if (!$result) {
                throw new Exception('Fail set to ProgressCourses', 500);
            }

            return [
                'is_complete' => $is_complete,
                'eval_result' => $eval_result ?? '',
                'error_text' => '',
                'echo_text' => $_SESSION['echo_system_var']
            ];

        } catch (\ParseError|\ErrorException|\Error $e) {
            return [
                'is_complete' => false,
                'eval_result' => '',
                'error_text' => $e->getMessage(),
                'echo_text' => $_SESSION['echo_system_var']
            ];
        }
    }
}
