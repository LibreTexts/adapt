<?php

namespace App\Http\Controllers\Auth;

use App\Analytics;
use App\Enrollment;
use App\Helpers\Helper;
use App\Http\Requests\LoginAsRequest;
use App\User;
use App\Course;
use App\Assignment;
use App\Exceptions\Handler;
use Exception;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Http\Request;
use MiladRahimi\Jwt\Exceptions\InvalidSignatureException;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;

class UserController extends Controller
{

    /**
     * @param Request $request
     * @return JsonResponse
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function current(Request $request): JsonResponse
    {
        if ($request->user()) {
            $request->user()->is_tester_student = DB::table('tester_students')
                ->where('student_user_id', $request->user()->id)
                ->exists();
            $request->user()->is_webwork_macro_editor = Helper::isWebworkMacroEditor();
            $request->user()->is_webwork_macro_co_editor = DB::table('webwork_macro_co_editors')
                ->where('user_id', $request->user()->id)
                ->exists();
            if ($request->user()->is_tester_student) {
                $request->user()->email = '';
            }
            $request->user()->linked_accounts = session()->get('linked_accounts');
            $request->user()->is_developer = $request->user()->isDeveloper();
            $request->user()->logged_in_as_user = session()->get('original_user_id');
            $request->user()->is_instructor_logged_in_as_student = is_numeric(session()->get('original_user_id')) || $request->user()->fake_student;

        }

        $sid = session()->get('sid');
        $request->user()->back_channel_single_logout = DB::table('logged_in_user_tokens')
            ->where('sid', $sid)
            ->where('logged_out', 1)
            ->exists();
        if ($request->user()->back_channel_single_logout) {
            DB::beginTransaction();
            DB::table('logged_in_user_tokens')
                ->where('sid', $sid)
                ->delete();
            try {
                auth()->logout();
                DB::commit();
            } catch (\Exception $e) {
                DB::rollback();
                return response()->json(['message' => 'Token invalidation failed', 'error' => $e->getMessage()], 500);
            }
            return response()->json(['message' => 'User has been logged out due to backchannel single logout.'], 200);
        }
        return response()->json($request->user());
    }

    public function validateSignature($content, $secret): bool
    {

        //https://developer.okta.com/blog/2019/02/04/create-and-verify-jwts-in-php
        //verify
        // split the token
        $tokenParts = explode('.', $content);
        if (!(isset($tokenParts[0]) && isset($tokenParts[1]) && isset($tokenParts[2]))) {
            return false;
        }
        $header = $tokenParts[0];
        $payload = $tokenParts[1];
        $signatureProvided = $tokenParts[2];
        $signature = hash_hmac('sha256', $header . "." . $payload, $secret, true);
        $base64UrlSignature = $this->base64UrlEncode($signature);

        // verify it matches the signature provided in the token
        return ($base64UrlSignature === $signatureProvided);
        //
    }

    function base64UrlEncode($text)
    {
        return str_replace(
            ['+', '/', '='],
            ['-', '_', ''],
            base64_encode($text)
        );
    }

    /**
     * @param Request $request
     * @param Analytics $analytics
     * @return array
     * @throws Exception
     */
    public function autoLogin(Request $request, Analytics $analytics): array
    {

        try {
            $response['type'] = 'error';
            $claims = $analytics->hasAccess($request);
            $id = $claims['id'];
            if (in_array($id, [1, 5])) {
                $response['message'] = "$id is an admin user.";
                return $response;
            }
            $user = User::find($id);
            if (!$user) {
                $response['message'] = "$id is not a valid user id.";
                return $response;
            }
            if ($user->role !== 2) {
                $response['message'] = "$id is a user who is not an instructor.";
                return $response;
            }
            $token = \JWTAuth::claims(['analytics' => 1])->fromUser($user);
            $response['type'] = 'success';
            $response['token'] = $token;
        } catch (InvalidSignatureException $e) {
            $response['message'] = 'InvalidSignatureException: cannot log do auto-login.';
            return $response;
        } catch (Exception $e) {
            $response['message'] = $e->getMessage() ?: 'Cannot log in due to JWT error.';
        }
        return $response;

    }

    /**
     * @param Request $request
     * @return array
     */
    public function getSession(Request $request): array
    {
        return $request->session()->all();
    }

    /**
     * @param Request $request
     * @param User $User
     * @param Assignment $Assignment
     * @return array
     * @throws Exception
     */
    public function toggleStudentView(Request $request, User $User, Assignment $Assignment): array
    {
        $response['type'] = 'error';
        $course_id = $request->course_id;
        $assignment_id = $request->assignment_id;
        $route_name = $request->route_name;
        $current_user = $request->user();

        $authorized = Gate::inspect('toggleStudentView', $User);

        if (!$authorized->allowed()) {
            $response['message'] = $authorized->message();
            return $response;
        }


        try {
            if (!$course_id) {
                $course_id = $Assignment->find($assignment_id)->course->id;
            }

            if (!$current_user->instructor_user_id) {
                //remember who they are and log them in as fake student
                $new_user = Course::find($course_id)->fakeStudent();
                $new_user->instructor_user_id = $current_user->id;
                $new_user->save();
                $new_user_types = 'students';


            } else {
                $user_id = $current_user->instructor_user_id;
                $new_user = $User::find($user_id);
                $current_user->instructor_user_id = null;
                $current_user->save();
                $new_user_types = 'instructors';
            }

            $response['type'] = 'success';
            $response['new_route_name'] = $this->getNewRouteFromOldRouteAndNewUserType($route_name, $new_user_types);
            $response['token'] = \JWTAuth::fromUser($new_user);
        } catch (Exception $e) {
            $h = new Handler(app());
            $h->report($e);
            $response['message'] = "There was an error switching views. Please try again or contact us for assistance.";

        }

        return $response;


    }

    /**
     * @param string $route_name
     * @param string $new_user_types
     * @return string
     */
    public function getNewRouteFromOldRouteAndNewUserType(string $route_name, string $new_user_types): string
    {

        $current_user_types = $new_user_types === 'students' ? 'instructors' : 'students';
        $route_name_without_role = str_replace($current_user_types . '.', '', $route_name);
        if ($route_name === 'instructors.assignments.questions') {
            return 'questions.view';
        }
        if ($route_name === 'questions.view') {
            return 'questions.view';
        } else if (in_array($route_name_without_role, ['assignments.index', 'courses.index', 'assignments.summary'])) {
            return "$new_user_types.$route_name_without_role";
        } else {
            return "$new_user_types.courses.index";
        }


    }

    /**
     * @param Assignment $assignment
     * @return array
     * @throws Exception
     */
    public function loginToAssignmentAsFormativeStudent(Assignment $assignment): array
    {

        $response['type'] = 'error';
        try {
            if (!$assignment->course->formative && !$assignment->formative) {
                $response['message'] = "This is not a formative assignment.";
                return $response;
            }

            $user = new User();
            $user->first_name = '';
            $user->last_name = '';
            $user->role = 3;
            $user->email = substr(sha1(mt_rand()), 17, 25);
            $user->student_id = '';
            $user->time_zone = 'America/Los_Angeles';
            $user->formative_student = 1;
            $user->save();
            $section = DB::table('sections')
                ->where('course_id', $assignment->course->id)
                ->first();
            if (!$section) {
                $response['message'] = "This assignment needs to be associated with a course with at least one section.";
                return $response;
            }
            $enrollment = new Enrollment();
            $enrollment->section_id = $section->id;
            $enrollment->course_id = $assignment->course->id;
            $enrollment->user_id = $user->id;
            $enrollment->save();
            session()->put('formative_student_user_id', $user->id);

            $response['type'] = 'success';
            $response['token'] = \JWTAuth::fromUser($user);
            $response['success'] = true;
        } catch (Exception $e) {
            $h = new Handler(app());
            $h->report($e);
            $response['message'] = "There was an error logging you in as a formative student.  Please try again or contact us for assistance.";
        }
        return $response;


    }

    /**
     * @param Request $request
     * @param Course $Course
     * @return array
     * @throws Exception
     */
    public function loginAsStudentInCourse(Request $request, Course $Course): array
    {
        $response['type'] = 'error';

        $course = $Course->where('id', $request->course_id)->first();

        $authorized = Gate::inspect('loginAsStudentInCourse', [$course, $request->student_user_id]);
        if (!$authorized->allowed()) {
            $response['message'] = $authorized->message();
            return $response;
        }

        try {

            $new_user = User::find($request->student_user_id);
            if (Helper::isAdmin()) {
                session()->put(['admin_user_id' => $request->user()->id]);
            }
            if ($request->user()->role === 2) {
                session()->put(['original_user_id' => $request->user()->id]);
            }
            $response['type'] = 'success';
            $response['token'] = \JWTAuth::fromUser($new_user);
            $response['success'] = true;
        } catch (Exception $e) {
            $h = new Handler(app());
            $h->report($e);
            $response['message'] = "There was an error logging you in as this student.  Please try again or contact us for assistance.";
        }
        return $response;

    }

    /**
     * @param LoginAsRequest $request
     * @param User $user
     * @return array
     * @throws Exception
     */
    public function loginAs(LoginAsRequest $request, User $user): array
    {
        $response['type'] = 'error';
        $user_info = [];

        try {
            if (strpos($request->user, "https://") === false && !ctype_digit($request->user)) {
                // A user was selected from the autocomplete dropdown: "First Last --- email@example.com".
                $user_info = explode(' --- ', $request->user);
                $email = $user_info[1];
                $new_user = User::where('email', $email)->first();
            } elseif (ctype_digit($request->user)) {
                // A bare assignment id: log in as that assignment's course instructor.
                $assignment = Assignment::find($request->user);
                if (!$assignment) {
                    $response['message'] = "$request->user is not a valid assignment id.";
                    return $response;
                }
                $new_user = User::find($assignment->course->user_id);
                $email = $new_user->email;
            } else {
                // A URL containing an assignment id anywhere after "assignments/".
                $pattern = "/\/assignments\/(\d+)/";
                if (preg_match($pattern, $request->user, $matches)) {
                    $assignment_id = $matches[1];
                    $assignment = Assignment::find($assignment_id);
                    if (!$assignment) {
                        $response['message'] = "$assignment_id is not a valid assignment id.";
                        return $response;
                    }
                    $new_user = User::find($assignment->course->user_id);
                    $email = $new_user->email;
                } else {
                    $response['message'] = "That is not a valid URL to log in as.";
                    return $response;
                }

            }
            $authorized = Gate::inspect('loginAs', [$user, $email]);
            if (!$authorized->allowed()) {
                $response['message'] = $authorized->message();
                return $response;
            }
            if (Helper::isAdmin()) {
                session()->put(['admin_user_id' => $request->user()->id]);
            }
            session()->put(['original_user_id' => $request->user()->id]);
            $linked_accounts = Helper::getLinkedAccounts($new_user->id);
            session()->put('linked_accounts', $linked_accounts);
            $new_user->linked_accounts = $linked_accounts;
            $response['type'] = 'success';
            $response['token'] = \JWTAuth::fromUser($new_user);
        } catch (Exception $e) {
            $h = new Handler(app());
            $h->report($e);
            $user_info_message = $user_info[0] ?? "the user";
            $response['message'] = "There was an error logging in as $user_info_message.  Please try again or contact us for assistance.";
        }
        return $response;

    }

    /**
     * @return array|RedirectResponse
     * @throws Exception
     */
    public function exitLoginAs()
    {
        if (!session('original_user_id')) {
            return redirect()->action('Auth\LoginController@logout');
        }
        $response['type'] = 'error';
        try {
            $original_user_id = 0;
            if (session('admin_user_id')) {
                $original_user_id = session('admin_user_id');
            } else if (session('original_user_id')) {
                $original_user_id = session('original_user_id');
            }
            $new_user = User::find($original_user_id);
            session()->forget('original_user_id');
            session()->forget('admin_user_id');
            $linked_accounts = Helper::getLinkedAccounts($new_user->id);
            session()->put('linked_accounts', $linked_accounts);
            $new_user->linked_accounts = $linked_accounts;
            DB::beginTransaction();
            $response['type'] = 'success';
            $response['token'] = \JWTAuth::fromUser($new_user);
            DB::commit();
        } catch (Exception $e) {
            DB::rollback();
            $h = new Handler(app());
            $h->report($e);
            $response['message'] = "There was an error exiting the login as.  Please try again or contact us for assistance.";
        }
        return $response;

    }

    /**
     * @param Request $request
     * @param User $user
     * @return array
     * @throws Exception
     */
    public function getPotentialWebworkEditors(Request $request, User $user): array
    {
        $response['type'] = 'error';
        $authorized = Gate::inspect('getPotentialWebworkEditors', $user);
        if (!$authorized->allowed()) {
            $response['message'] = $authorized->message();
            return $response;
        }
        try {
            $users
                = DB::table('users')
                ->orderBy('last_name')
                ->select(DB::raw('CONCAT(first_name, " " , last_name, " --- ", email) AS user'))
                ->where('email', '<>', null)
                ->where('central_identity_id', '<>', null)
                ->whereIn('users.role', [2,5])
                ->where('users.testing_student', 0)
                ->get()
                ->pluck('user');

            $response['users'] = $users;
            $response['type'] = 'success';

        } catch (Exception $e) {
            $h = new Handler(app());
            $h->report($e);
            $response['message'] = "There was an error getting all the potential webwork macro editors.  Please try again or contact us for assistance.";

        }
        return $response;
    }

    /**
     * @param Request $request
     * @param User $user
     * @return array
     * @throws Exception
     */
    public function getAll(Request $request, User $user): array
    {
        $response['type'] = 'error';
        if ($request->user()->id !== 7665) {
            $authorized = Gate::inspect('getAll', $user);
            if (!$authorized->allowed()) {
                $response['message'] = $authorized->message();
                return $response;
            }
        }
        try {
            $users
                = DB::table('users')
                ->orderBy('last_name')
                ->select(DB::raw('CONCAT(first_name, " " , last_name, " --- ", email) AS user'))
                ->where('email', '<>', null)
                ->where('central_identity_id', '<>', null)
                ->where('users.formative_student', 0)
                ->where('users.testing_student', 0);
            if ($request->user()->id == 7665) {
                $users = $users->where('email', 'LIKE', '%estrellamountain.edu%')
                    ->where('role', 2)
                    ->where('id', '<>', 7665);
            }
            $users = $users->get()
                ->pluck('user');
            $response['users'] = $users;
            $response['type'] = 'success';

        } catch (Exception $e) {
            $h = new Handler(app());
            $h->report($e);
            $response['message'] = "There was an error getting all users.  Please try again or contact us for assistance.";

        }
        return $response;
    }


    public function getAuthenticatedUser(Request $request)

    {
        try {
            $payload = \JWTAuth::parseToken()->getPayload();
            if (!$user = \JWTAuth::parseToken()->authenticate()) {
                return response()->json(['user_not_found'], 404);
            }

        } catch (Tymon\JWTAuth\Exceptions\TokenExpiredException $e) {

            return response()->json(['token_expired'], $e->getStatusCode());

        } catch (Tymon\JWTAuth\Exceptions\TokenInvalidException $e) {

            return response()->json(['token_invalid'], $e->getStatusCode());

        } catch (Tymon\JWTAuth\Exceptions\JWTException $e) {

            return response()->json(['token_absent'], $e->getStatusCode());

        }

        // the token is valid and we have found the user via the sub claim
        return [\JWTAuth::parseToken()->getPayload(), $request->all()];
    }
}
