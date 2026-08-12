<?php

namespace App\Http\Controllers;

use App\Enrollment;
use App\Exceptions\Handler;
use App\LtiAssignmentsAndGradesUrl;
use App\LtiGradePassback;
use App\LtiLaunch;
use App\LtiNamesAndRolesUrl;
use App\LtiRegistration;
use App\LtiToken;
use App\OIDC;
use App\Section;
use App\User;
use Exception;
use GuzzleHttp\Middleware;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Redirector;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Jose\Component\KeyManagement\JWKFactory;
use Overrides\IMSGlobal\LTI;
use App\Custom\LTIDatabase;
use App\Assignment;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Psr\Http\Message\RequestInterface;


class LTIController extends Controller
{

    /**
     * @param OIDC $OIDC
     * @return mixed
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function getUser(OIDC $OIDC)
    {
        $response['type'] = 'error';
        try {
            $user = User::where('id', session()->get('lti_user_id'))->firstOrFail();
            $email = $user->email;
            if (!$user->central_identity_id) {
                $data = ['email' => $email,
                    'first_name' => $user->first_name,
                    'last_name' => $user->last_name,
                    'user_type' => $user->role === 2 ? 'instructor' : 'student',
                    'time_zone' => $user->time_zone];
                if (!app()->environment('local')) {
                    $oidc_response = $OIDC->autoProvision($data);
                    if ($oidc_response['type'] === 'success') {
                        $user->central_identity_id = $oidc_response['central_identity_id'];
                        $user->save();
                    } else {
                        /* Telegram::sendMessage([
                             'chat_id' => config('myconfig.telegram_channel_id'),
                             'parse_mode' => 'HTML',
                             'text' => "Unable to auto-provision User: $user->id. Error: " . json_encode($oidc_response)
                         ]);*/
                    }
                } else {
                    $user->central_identity_id = (string)Str::uuid();
                    $user->save();
                }
            } else {
                $lti_user_email = session()->get('lti_user_email');
                if ($lti_user_email && $lti_user_email !== $user->email) {
                    /*  go back to this at some point.
                     $email = $lti_user_email;
                     $user->email = $email;
                     $user->save();
                     $OIDC->changeEmail($user->central_identity_id, $lti_user_email);
                    */
                }
            }
            $token = \JWTAuth::fromUser($user);
            $response['token'] = $token;
            $response['type'] = 'success';
        } catch (ModelNotFoundException $e) {
            $h = new Handler(app());
            $h->report($e);
            $response['message'] = "It looks like your LTI User ID was not valid.  Instructors using Canvas should make sure that they have checked 'Load This Tool In A New Tab' (where you selected ADAPT as an External Tool from within Canvas).";

        } catch (Exception $e) {
            $h = new Handler(app());
            $h->report($e);
            $response['message'] = 'We could not link up your LMS user account with ADAPT.  Please try again or contact us for assistance.';

        }
        return $response;

    }

    /**
     * @param Request $request
     * @param Assignment $assignment
     * @return array
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function linkAssignmentToLMS(Request $request, Assignment $assignment): array
    {
        $response['type'] = 'error';
        $lms_resource_link_id = $request->lms_resource_link_id;
        $authorized = Gate::inspect('linkAssignmentToLMS', $assignment);
        if (!$authorized->allowed()) {
            $response['message'] = $authorized->message();
            return $response;
        }
        $lms_resource_link_id_exists = DB::table('assignments')
            ->where('lms_resource_link_id', $lms_resource_link_id)
            ->first();
        if ($lms_resource_link_id_exists) {
            $response['message'] = "That LMS resource is already linked to another assignment.";
            return $response;
        }

        try {
            DB::beginTransaction();
            DB::table('assignments')
                ->where('id', $assignment->id)
                ->update(['lms_resource_link_id' => $lms_resource_link_id,
                    'lms_assignment_name' => session()->get('lms_assignment_name')]);
            DB::table('courses')
                ->where('id', $assignment->course->id)
                ->update(['lms_course_name' => session()->get('lms_course_name')]);

            $current_lti_launch_by_assignment_id = LtiLaunch::where('assignment_id', $assignment->id)
                ->where('user_id', $request->user()->id)
                ->where('launch_id', '<>', session()->get('lms_launch_id'))
                ->first();
            if ($current_lti_launch_by_assignment_id) {
                //in case some manual linking happened, unlink it
                $current_lti_launch_by_assignment_id->delete();
            }
            $launch = LtiLaunch::where('launch_id', session()->get('lms_launch_id'))->first();

            $launch->update(['assignment_id' => $assignment->id]);
            /*$launch_data = json_decode($launch->jwt_body, true);
            $assignments_and_grades_url = $launch_data["https://purl.imsglobal.org/spec/lti-ags/claim/endpoint"]["lineitem"];
            LtiAssignmentsAndGradesUrl::updateOrCreate(
                ['assignment_id' => $assignment->id],
                ['url' => $assignments_and_grades_url]
            );
            $names_and_roles_url = $launch_data["https://purl.imsglobal.org/spec/lti-nrps/claim/namesroleservice"]["context_memberships_url"];
            /*LtiNamesAndRolesUrl::updateOrCreate(
                ['course_id' => $assignment->course->id],
                ['url' => $names_and_roles_url]
            );
            Look at the key length?
            SQLSTATE[23000]: Integrity constraint violation: 1062 Duplicate entry 'https://some-school.instructure.com/api/lti/courses/79511/names_and_' for key 'lti_names_and_roles_urls.lti_names_and_roles_urls_url_unique' (SQL: insert into `lti_names_and_roles_urls` (`course_id`, `url`, `updated_at`, `created_at`) values (6992, https://peralta.instructure.com/api/lti/courses/79511/names_and_roles, 2025-05-22 23:20:15, 2025-05-22 23:20:15))'*/
            session()->forget('lms_launch_id');
            DB::commit();
            $response['assignment_id'] = $assignment->id;
            $response['type'] = 'success';
        } catch (Exception $e) {
            DB::rollBack();
            $h = new Handler(app());
            $h->report($e);
            $response['message'] = "There was an error linking the assignment to your LMS.  Please try again by refreshing the page or contact us for assistance.";

        }
        return $response;
    }

    public function initiateLoginRequest(Request $request)
    {

        $campus_id = basename($request['target_link_uri']);
        $launch_url = request()->getSchemeAndHttpHost() . "/api/lti/redirect-uri/$campus_id";
        $is_moodle = isset($request['iss']) && strpos($request['iss'], 'moodle') !== false;
        $is_brightspace = isset($request['iss'])
            && LtiRegistration::isBrightSpace($request['iss']);
        if ($is_brightspace || $is_moodle || $campus_id === 'configure' || $campus_id === 'redirect-uri') {
            $campus_id = '';
            $launch_url = request()->getSchemeAndHttpHost() . "/api/lti/redirect-uri";
        }

        // file_put_contents(base_path() . '//lti_log.text', "Initiate login request:" . print_r($request->all(), true) . "\r\n", FILE_APPEND);
        try {
            Storage::disk('s3')->put("lti-logs/$campus_id.txt", print_r($request->all(), true));
            // file_put_contents(base_path() . '//lti_log.text', "Initiate login request:" . print_r($request->all(), true) . "\r\n", FILE_APPEND);
        } catch (Exception $e) {
            $h = new Handler(app());
            $h->report($e);
        }
        LTI\LTI_OIDC_Login::new(new LTIDatabase())
            ->do_oidc_login_redirect($launch_url, $campus_id, $request->all())
            ->do_redirect();

    }

    /**
     * @param Assignment $assignment
     * @param User $user
     * @param LtiLaunch $ltiLaunch
     * @param LtiGradePassback $ltiGradePassback
     * @param string $campus_id
     * @return Application|RedirectResponse|Redirector
     * @throws Exception
     */
    public function authenticationResponse(Assignment       $assignment,
                                           User             $user,
                                           LtiLaunch        $ltiLaunch,
                                           LtiGradePassback $ltiGradePassback,
                                           string           $campus_id = '')
    {


        try {
            $launch = LTI\LTI_Message_Launch::new(new LTIDatabase())
                ->validate();
            $url = $campus_id === ''
                ? request()->getSchemeAndHttpHost() . "/api/lti/redirect-uri"
                : request()->getSchemeAndHttpHost() . "/api/lti/redirect-uri/$campus_id";

            if ($launch->is_deep_link_launch()) {
                //this configures the Deep Link
                $is_moodle = strpos($launch->get_launch_data()['iss'], 'moodle') !== false;
                if ($is_moodle) {
                    //moodle needs the custom params to be some sort of object (even though it's really empty).  As a hack I'm just sending them
                    $resource = LTI\LTI_Deep_Link_Resource::new()
                        ->set_url($url)
                        ->set_custom_params(json_decode('{"name" : "value"}'));
                } else {
                    $resource = LTI\LTI_Deep_Link_Resource::new()
                        ->set_url($url);
                }
                $launch->get_deep_link()
                    ->output_response_form([$resource]);
                exit;
            }
            $launch_id = $launch->get_launch_id();

            $custom = isset($launch->get_launch_data()['https://purl.imsglobal.org/spec/lti/claim/custom']) ?
                $launch->get_launch_data()['https://purl.imsglobal.org/spec/lti/claim/custom'] : [];

            $email = $launch->get_launch_data()['email'] ?? null;
            if (!$email) {
                echo "We can't seem to get this user's email.  Typically this happens if you're trying to connect in Student View mode or if you neglected to set the Privacy Level to Public when configuring this tool.";
                exit;
            }
            //check by email first because this is the most recent account *******THINK ABOUT THIS!!!!!!********
            $sub = $launch->get_launch_data()['sub'];
            $lti_user = null;
            $launch_redirect = null;
            if ($sub) {
                $lti_user = $user->where('lms_user_id', $sub)->first();
            }
            if (!$lti_user) {
                $launch_redirect = DB::table('launch_redirects')
                    ->where('original_email', $email)
                    ->first();
                if ($launch_redirect) {
                    $redirect_email = $launch_redirect->redirect_email;
                    $lti_user = $user->where('email', $redirect_email)->first();
                    if (!$lti_user) {
                        throw new Exception("Redirect email {$redirect_email} does not map to a user.");
                    }
                    $email = $redirect_email;
                }
            }

            if (!$lti_user) {
                $lti_user = $user->where('email', $email)->first();
            }

            if (!$lti_user) {
                $lti_user = User::create([
                    'first_name' => $launch->get_launch_data()['given_name'],
                    'last_name' => $launch->get_launch_data()['family_name'],
                    'email' => $email,
                    'role' => 3,
                    'time_zone' => 'America/Los_Angeles',
                    'lms_user_id' => $sub,
                    'email_verified_at' => now(),
                ]);
            } else {
                ///eventually I shouldn't need the following code since they'll all be new
                if (!$lti_user->sub && !$launch_redirect) {
                    $lti_user->lms_user_id = $sub;
                    $lti_user->save();
                }
            }
            DB::table('users')->where('instructor_user_id', $lti_user->id)->update(['instructor_user_id' => null]);
            session()->forget('original_user_id');
            session()->forget('admin_user_id');
            $lms_course_name = $launch->get_launch_data()["https://purl.imsglobal.org/spec/lti/claim/context"]['title'] ?? 'No LMS course name provided';
            $lms_assignment_name = $launch->get_launch_data()["https://purl.imsglobal.org/spec/lti/claim/resource_link"]['title'] ?? 'No LMS title provided';
            session()->put('lms_course_name', trim($lms_course_name));
            session()->put('lms_assignment_name', trim($lms_assignment_name));
            //Canvas opens in a new window so I use this to make sure that students don't see the breadcrumbs
            //Blackboard automatically opens in an iframe so this session value will do nothing


            //  file_put_contents(base_path() . '//lti_log.text', "Launch:" . print_r($launch->get_launch_data(), true) . "\r\n", FILE_APPEND);
            //  file_put_contents(base_path() . '//lti_log.text', "Launch:" . print_r($request->all(), true) . "\r\n", FILE_APPEND);

            //if this has not been configured yet, there will be no resource link id
            $resource_link_id = $launch->get_launch_data()['https://purl.imsglobal.org/spec/lti/claim/resource_link']['id'];
            $linked_assignment = $assignment->where('lms_resource_link_id', $resource_link_id)->first();
            if (!$linked_assignment && isset($custom['canvas_assignment_id'])) {
                //need to think this logic through
                $linked_assignment = $assignment->where('lms_assignment_id', $custom['canvas_assignment_id'])->first();
            }
            $lms_launch_in_new_window = (int)($launch->get_launch_data()['iss'] === 'https://blackboard.com');

            //This code was also testing for Canvas on Dev server, so you can set $lms_launch_in_new_window to true if needed.
            if (!$lms_launch_in_new_window) {
                session()->put('lti_user_id', $lti_user->id);
                session()->put('lti_user_email', $email);
            }
            if ($lms_launch_in_new_window) {
                $lti_token = \JWTAuth::fromUser($lti_user);
                $bytes = bin2hex(random_bytes(20));
                $ltiToken = new LtiToken();
                $ltiToken->lti_token = $lti_token;
                $lti_token_id = "$lti_user->id-$bytes";
                $ltiToken->lti_token_id = $lti_token_id;
                $ltiToken->save();
            }
            if ($linked_assignment) {
                $lti_launch_by_user_and_assignment = $ltiLaunch
                    ->where('user_id', $lti_user->id)
                    ->where('assignment_id', $linked_assignment->id)
                    ->first();
                if ($lti_user->role === 3) {
                    if (!$lti_launch_by_user_and_assignment) {
                        $lti_launch_by_user_and_assignment = new LtiLaunch();
                        $lti_launch_by_user_and_assignment->user_id = $lti_user->id;
                        $lti_launch_by_user_and_assignment->assignment_id = $linked_assignment->id;
                        $lti_launch_by_user_and_assignment->launch_id = $launch_id;
                        $lti_launch_by_user_and_assignment->jwt_body = json_encode($launch->get_launch_data());
                        $lti_launch_by_user_and_assignment->save();

                        //just in case the instructor changed the LMS late, let's passback any score
                        $score_exists = DB::table('scores')
                            ->where('user_id', $lti_user->id)
                            ->where('assignment_id', $linked_assignment->id)
                            ->first();
                        if ($score_exists && $linked_assignment->lms_grade_passback === 'automatic') {
                            $ltiGradePassback->initPassBackByUserIdAndAssignmentId($score_exists->score, $lti_launch_by_user_and_assignment);
                        }
                    } else {
                        //use the most recently validated launch information
                        $ltiLaunch
                            ->where('user_id', $lti_user->id)
                            ->where('assignment_id', $linked_assignment->id)
                            ->update(['launch_id' => $launch_id,
                                'jwt_body' => json_encode($launch->get_launch_data())]);
                        $ltiGradePassback->where('user_id', $lti_user->id)
                            ->where('assignment_id', $linked_assignment->id)
                            ->update(['launch_id' => $launch_id]);
                    }
                    $courses = DB::table('assignments')
                        ->join('courses', 'courses.id', '=', 'assignments.course_id')
                        ->join('sections', 'courses.id', '=', 'sections.course_id')
                        ->join('users', 'courses.user_id', '=', 'users.id')
                        ->where('assignments.id', $linked_assignment->id)
                        ->select('courses.id AS course_id', 'users.time_zone AS instructor_time_zone')
                        ->get();
                    if (count($courses) === 1) {
                        $course = $courses[0];
                        $course_id = $course->course_id;
                        $Section = new Section();
                        $section = $Section->where('course_id', $course_id)->first();
                        if ($section->course->enrollments->isNotEmpty()) {
                            $enrolled_user_ids = $section->course->enrollments->pluck('user_id')->toArray();
                            if (!in_array($lti_user->id, $enrolled_user_ids)) {
                                $lti_user->time_zone = $course->instructor_time_zone;
                                $lti_user->student_id = $sub;
                                $lti_user->save();
                                $enrollment = new Enrollment();
                                $enrollment->completeEnrollmentDetails($lti_user->id, $section, $course_id, !$lti_user->fake_student);
                                $assignment = Assignment::find($linked_assignment->id);
                                if (!$assignment->shown) {
                                    $assignment->shown = 1;
                                    $assignment->save();
                                }
                            }
                        }
                    }
                }
                return $lms_launch_in_new_window ?
                    redirect("/launch-in-new-window/$lti_token_id/init/$linked_assignment->id")
                    : redirect("/init-lms-assignment/$linked_assignment->id");
            } else {
                if (!LtiLaunch::where('launch_id', $launch_id)->exists()) {
                    $lti_launch_by_user_and_assignment = new LtiLaunch();
                    $lti_launch_by_user_and_assignment->user_id = $lti_user->id;
                    $lti_launch_by_user_and_assignment->assignment_id = null;
                    $lti_launch_by_user_and_assignment->launch_id = $launch_id;
                    $lti_launch_by_user_and_assignment->jwt_body = json_encode($launch->get_launch_data());
                    $lti_launch_by_user_and_assignment->save();
                }
                session()->put('lms_launch_id', $launch_id);

                return $lms_launch_in_new_window ?
                    redirect("/launch-in-new-window/$lti_token_id/link/$resource_link_id")
                    : redirect("/instructors/link-assignment-to-lms/$resource_link_id");
            }
        } catch (LTI\LTI_Exception $e) {
            $h = new Handler(app());
            $h->report($e);
            if ($e->getMessage() === 'State not found') {
                echo "We were not able to log you in.  Are you using Safari?  If so, please try Chrome, Edge, or Firefox.  In addition, please make sure that you have 3rd Party Cookies enabled in your browser.  These <a href='https://commons.libretexts.org/insight/cookies-required' target='_blank'>instructions</a> may help.";
            } else {
                echo "We were unable to log you in.  Error message: {$e->getMessage()}.  Please contact us at <a href='mailto:adapt@libretexts.org' target='_blank' rel='noopener noreferrer''>adapt@libretexts.org</a> so that we can help you troubleshoot this.";
            }

        } catch (Exception $e) {
            $h = new Handler(app());
            $h->report($e);
            echo "There was an error logging you in via LTI.  Please refresh the page and try again or contact us for assistance.\r\n";
            if (app()->environment('dev')) {
                echo "Error: . {$e->getMessage()}";
            }
        }
    }


    public
    function configure($launch_id)
    {

        $launch = LTI\LTI_Message_Launch::from_cache($launch_id, new LTIDatabase());

        if (!$launch->is_deep_link_launch()) {
            echo "Not a deep link.";
            exit;
        }
        //file_put_contents(base_path() . '//lti_log.text', "Launch id: $launch_id", FILE_APPEND);
        $resource = LTI\LTI_Deep_Link_Resource::new()
            ->set_url(request()->getSchemeAndHttpHost() . "/api/lti/redirect-uri");
        // file_put_contents(base_path() . '//lti_log.text', print_r((array)$launch->get_deep_link(), true), FILE_APPEND);
        $launch->get_deep_link()
            ->output_response_form([$resource]);
    }

    public
    function publicJWK()
    {
        return '{"keys" :[{
            "d" : "tkdUVHX4yVKzkK1pPLKO11QXzteTcBF4QJIVGJ6ZjwBf7WeBIXzMrGli2XFSFum2yygrbkQlTF_Xr3yG5JC1NBK4aj4t0AE3Fy_89a_PmwFKa4aTQIPX73zP2bpFw0YHnejDzTAtdZ7HhKfB1FOKBzcF1ci-hb5rLax8mKBJ5IyIjJN-DtjBYwGr6CCYTNIJKF1Z8UT-TDYtZxj1YSvk32cka4ttMdUYdwrCKt-j1MsQiAlpA-437SxqlXUAX7ooutNCz-b-57h8_Sw7AnmO8USbtHi3Q5O__bpG_H7quv_t1WDGAoWFr6cOA2h_Kgx8WX1szMmiOPPZmpdu5YYHcQ",
            "e" : "AQAB",
            "n" : "8osiSa75nmqmakwNNocLA2N2huWM9At_tjSZOFX1r4-PDclSzxhMw-ZcgHH-E_05Ec6Vcfd75i8Z-Bxu4ctbYk2FNIvRMN5UgWqxZ5Pf70n8UFxjGqdwhUA7_n5KOFoUd9F6wLKa6Oh3OzE6v9-O3y6qL40XhZxNrJjCqxSEkLkOK3xJ0J2npuZ59kipDEDZkRTWz3al09wQ0nvAgCc96DGH-jCgy0msA0OZQ9SmDE9CCMbDT86ogLugPFCvo5g5zqBBX9Ak3czsuLS6Ni9Wco8ZSxoaCIsPXK0RJpt6Jvbjclqb4imsobifxy5LsAV0l_weNWmU2DpzJsLgeK6VVw",
            "p" : "-TEfpa5kz8Y6jCPJK6u5GMBXIniy1972X_HwyuqcUDZDyy2orr3rRj0sOtJoDHtC62_NnrhuvZYyW-cZ0nDzrzPj8ma-gCpbcgdRfOpEAeA6T_xjfN5KN3u3dHQ7e_qoBtCPJFhiB8Axmjs_NdbwUo0axqQB50QpbRv3gdid0qk",
            "q" : "-SuCu0BGnaed3VYa7GBAyNf74eNPSn3Ht9MwK1-9iFmC5T0CULHndUcV4Zzp-qwORSYEW_R2oyfDRM_MRCosSUEiHztMZLglJeZxtBx6MjH6vLaQwW7Ixkg-69kKct8H93tC7YNTqZ14gEwT_wBfmQGqfV6R12KgRJ1KQeSSJ_8",
            "dp" : "aPCeAjjZ7YHuP_wGCOUNUvYU-8hWkIAtwyPxIpMAdusTS6oTwlrqjK7QRIk9FhyGhv2TWwcSY7avyHIfNrcoeBzjHr7T9MdhsTiRwYgqUZvrEqoX_4rhOFJaZKlaL5DUV-JWlZi-18LBYNEYgoTcufcAUqzYvFrBE1jWt5DQjdk",
            "dq" : "E7OrDJ9SdhjQ1LWAP3yE4tyhIAVXOa6kYhai0mspk2RwgyvFyReoE4_hXQuJPLbqEfGlmpfD4ba9K-26WxFymwA5cHrB2Zzt4wdLqlAuIVXuW4mb_I-D9Jm1z_RDbT3RZXIropglv12iL5LUae9fn7uP_YXCxmMYBRTi0D8Ah4U",
            "qi" : "YwLEhy55SQucj2vQqSO1dqn2YiB2ARHBA83QKb1PHflkTNGn3mR_gLow-xU7BmTmA2-9CeDHiJrD181gb48XbI24Nn4QXAjS-mYYIpFASR739UI4W5wyyOCMyFtT6OupEgkqKw_swITU1GHKYI-lB_-y0Q-XSdLmuP6ZkkdAQao",
            "alg" : "RS256",
            "kid" : "58f36e10-c1c1-4df0-af8b-85c857d1634f",
            "kty" : "RSA",
            "use" : "sig"}]}';
    }

    public
    function jsonConfig($id)
    {
        $app_url = config('app.url');
        $title = config('app.name');
        return [
            'title' => $title,
            'scopes' => [
                0 => 'https://purl.imsglobal.org/spec/lti-ags/scope/lineitem',
                1 => 'https://purl.imsglobal.org/spec/lti-ags/scope/lineitem.readonly',
                2 => 'https://purl.imsglobal.org/spec/lti-ags/scope/result.readonly',
                3 => 'https://purl.imsglobal.org/spec/lti-ags/scope/score',
                4 => 'https://purl.imsglobal.org/spec/lti-nrps/scope/contextmembership.readonly',
            ],
            'extensions' => [
                0 => [
                    'platform' => 'canvas.instructure.com',
                    'settings' => [
                        'placements' => [
                            0 => [
                                'placement' => 'assignment_selection',
                                'message_type' => 'LtiDeepLinkingRequest',
                                'target_link_uri' => $app_url . '/api/lti/configure/' . $id,
                            ],
                        ],
                        'assignment_selection' => [
                            'placement' => 'assignment_selection',
                            'message_type' => 'LtiDeepLinkingRequest',
                            'target_link_uri' => $app_url . '/api/lti/configure/' . $id,
                        ],
                    ],
                ],
            ],
            'public_jwk' => json_decode($this->publicJWK(), true)['keys'][0],
            'description' => 'ADAPT',
            'custom_fields' => [
            ],
            'target_link_uri' => $app_url . '/api/lti/redirect-uri/' . $id,
            'oidc_initiation_url' => $app_url . '/api/lti/oidc-initiation-url',
        ];

    }

    /**
     * @param Request $request
     * @param LtiToken $LtiToken
     * @return array
     * @throws Exception
     */
    public
    function getTokenByLtiTokenId(Request $request, LtiToken $LtiToken)
    {

        $response['type'] = 'error';
        try {
            $ltiToken = $LtiToken->where('lti_token_id', $request->lti_token_id)->first();
            if (!$ltiToken) {
                throw new Exception("No token found.");
            }
            $response['token'] = $ltiToken->lti_token;
            $response['type'] = 'success';
            $ltiToken->where('lti_token_id', $request->lti_token_id)->delete();
        } catch (Exception $e) {
            $response['message'] = "That is not a valid LTI token ID.";
            $h = new Handler(app());
            $h->report($e);
        }
        return $response;

    }

    public
    function refreshToken()
    {
        $response['type'] = 'error';
        try {
            $response['token'] = \JWTAuth::parseToken()->getPayload();
            $user = \JWTAuth::parseToken()->authenticate();
            $response['user'] = $user;
            $response['new_token'] = auth()->refresh();
            $response['type'] = 'success';
        } catch (Exception $e) {
            $h = new Handler(app());
            $h->report($e);
        }
        return $response;

    }


    public function publicKey(string $lms)
    {
        //currently just for Brightspace
        if ($lms === 'brightspace') {
            return '{
    "keys": [
        {
            "kty": "RSA",
            "n": "8grT-rRbqBOiw3XoQjxJmg4F_-edChksSVGfGrfq8LDknP3N5S6dYNK2xRojzpLgKopo0WUN56pwsaa4KOHH_1A9Ysb4lm3WRzqr_OSApPJ0u-B8Lw3YuPRvPn0azMIRsmVSZlVnbEw82fq9eehkC9zNxQe4nTeBiOGlWIk-NrXcWZECAvgEuiomyQ2yZxPt8aaAKZRJPvv5RYfJ5RUEk_ilA74rYNe1nQM4VHEhXU5uR4vPP6pFc8dsrUVdx0ceBjmW_pJN2FlVjAldgbQ4TKoSc5AJxg-KcosB6QFOSEnomYN-hyR0smfmR2yFgwpkY0q-fKtGY2ZOTD_EibuxoQ",
            "e": "AQAB",
            "use": "sig",
            "alg": "RS256",
            "kid": "ADAPT"
        }
    ]
}';
        }
    }

    /**
     * @return string|void
     */
    public function generateJWK()
    {
//openssl genpkey -algorithm RSA -out private.key -pkeyopt rsa_keygen_bits:2048
//openssl rsa -in private.key -pubout -out public.key
        if (!app()->environment('local')) {
            return "Can only run generateJWT locally";
        }
        $privateKeyPem = file_get_contents('/Users/franciscaparedes/private.key');

        $jwk = JWKFactory::createFromKey($privateKeyPem, null, [
            'use' => 'sig', // Signature key
            'alg' => 'RS256', // Algorithm
            'kid' => 'ADAPT', // Key ID
        ]);

        $publicJwk = ['keys' => [array_filter($jwk->toPublic()->all(), function ($key) {
            return $key !== 'd';
        })]];

        file_put_contents('/Users/franciscaparedes/Downloads/jwks.json', json_encode($publicJwk, JSON_PRETTY_PRINT));

        echo "JWK file generated: jwks.json\n";


    }

    public function getCanvasPublicKey()
    {
        $response = Http::withHeaders([
            'User-Agent' => 'ADAPT https://adapt.libretexts.org/ (adapt@libretexts.org)',
        ])->get('https://canvas.instructure.com/api/lti/security/jwks');

        $public_key_set = $response->json();
        dd($public_key_set);
    }
}
