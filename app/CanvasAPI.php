<?php

namespace App;

use Exception;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use stdClass;


class CanvasAPI extends Model
{

    /**
     * @var object
     */
    private $lti_registration;
    /**
     * @var int
     */
    private $user_id;

    public function __construct(object $lti_registration, int $user_id, array $attributes = array())
    {
        $this->lti_registration = $lti_registration;
        $this->user_id = $user_id;
        parent::__construct($attributes);
    }

    /**
     * @return mixed
     * @throws Exception
     */
    private function _updateAccessToken()
    {
        $lmsAccessToken = new LmsAccessToken();
        $lms_access_token = $lmsAccessToken->where('user_id', $this->user_id)->where('school_id', $this->lti_registration->school_id)->first();
        if (!app()->environment('local') && $lms_access_token->updated_at <= Carbon::now()->subMinutes(30)->toDateTimeString()) {
            // Pass the already school-scoped refresh_token in explicitly so getAccessToken()
            // doesn't independently re-query the DB (which was only filtering by user_id and
            // could grab a different school's row when a user has tokens for multiple schools).
            $result = $this->getAccessToken('', $lms_access_token->refresh_token);
            if ($result['type'] === 'success') {
                $lms_access_token->access_token = $result['access_token'];
                // Some Canvas instances rotate the refresh_token on every use. If we don't
                // persist the new one, the next refresh attempt fails with invalid_grant.
                if (isset($result['refresh_token'])) {
                    $lms_access_token->refresh_token = $result['refresh_token'];
                }
                $lms_access_token->save();
            } else {
                throw new Exception('Could not create the access token for the API call: ' . $result['message']);
            }
        }
        return $lms_access_token;
    }

    /**
     * @param int $course_id
     * @param array $assignment_info
     * @return array
     * @throws Exception
     */
    public function createAssignment(int $course_id, array $assignment_info): array
    {

        $lms_access_token = $this->_updateAccessToken();
        $external_tool_url = request()->getSchemeAndHttpHost() . "/api/lti/redirect-uri";
        ///When I did Kansas I didn't include a campus id...should probably fix this!!!
        if ($this->lti_registration->campus_id) {
            $external_tool_url .= "/{$this->lti_registration->campus_id}";
        }

        /*  $validate_external_tool_result = $this->_validateExternalTool($lms_access_token->access_token, $course_id, $external_tool_url);
          if ($validate_external_tool_result['type'] === 'error') {
              return $validate_external_tool_result;
          }*/
        $url = "/api/v1/courses/$course_id/assignments";
        $data = ['assignment[name]' => $this->addADAPT($assignment_info['name']),
            'assignment[submission_types][]' => 'external_tool',
            'assignment[points_possible]' => $assignment_info['total_points'] ?? 100,
            'assignment[grading_type]' => 'points',
            'assignment[allowed_attempts]' => -1,
            'assignment[external_tool_tag_attributes][url]' => $external_tool_url,
            'assignment[external_tool_tag_attributes][new_tab]' => true
        ];
        $instructions = '';
        if (isset($assignment_info['instructions'])) {
            $instructions = $assignment_info['instructions'];
        } else if (isset($assignment_info['description'])) {
            $instructions = $assignment_info['description'];
        }
        if ($instructions) {
            $data['assignment[description]'] = $instructions;
        }
        //the following won't exist for formative assignments
        if (isset($assignment_info['unlock_at'])) {
            $data['assignment[unlock_at]'] = $assignment_info['unlock_at'];
        }
        if (isset($assignment_info['unlock_at'])) {
            $data['assignment[due_at]'] = $assignment_info['due_at'];

        }
        return $this->_doCurl($lms_access_token->access_token, 'POST', $url, $data);
    }

    /**
     * @param string $access_token
     * @param $course_id
     * @param string $external_tool_url
     * @return array
     * @throws Exception
     */
    private function _validateExternalTool(string $access_token, $course_id, string $external_tool_url): array
    {


        $url = "/api/v1/courses/$course_id/external_tools/sessionless_launch";
        $data = ['url' => $external_tool_url];
        $url = $url . '?' . http_build_query($data);
        return $this->_doCurl($access_token, 'GET', $url, $data);

    }

    /**
     * @param int $course_id
     * @return array
     * @throws Exception
     */
    public function getAssignments(int $course_id): array
    {
        $lms_access_token = $this->_updateAccessToken();
        $url = "/api/v1/courses/$course_id/assignments?per_page=100";
        return $this->_doCurl($lms_access_token->access_token, 'GET', $url);
    }

    /**
     * @param int $course_id --- from Canvas
     * @param $assignment_id -- from Canvas
     * @param $assignment_info
     * @return array
     * @throws Exception
     */
    public function updateAssignment(int $course_id, $assignment_id, $assignment_info): array
    {
        //should change $assignment_id to being an int
        //$lms_access_token = $this->_updateAccessToken();
        $lms_message = new stdClass();
        $lms_message->id = $assignment_id;
        $lms_result['message'] = $lms_message;
        $lms_result['type'] = 'success';

        return $lms_result;


        $url = "/api/v1/courses/$course_id/assignments/$assignment_id";
        $data = [];
        if (isset($assignment_info['name'])) {
            $data['assignment[name]'] = $this->addADAPT($assignment_info['name']);
        }
        if (isset($assignment_info['instructions'])) {
            $data['assignment[description]'] = $assignment_info['instructions'];
        }
        if (isset($assignment_info['total_points'])) {
            $data['assignment[points_possible]'] = $assignment_info['total_points'];
        }

        if (isset($assignment_info['lms_assignment_group_id'])) {
            $data['assignment[assignment_group_id]'] = $assignment_info['lms_assignment_group_id'];
        }
        if (isset($assignment_info['position'])) {
            $data['assignment[position]'] = $assignment_info['position'];
        }
        if (isset($assignment_info['unlock_at'])) {
            $data['assignment[unlock_at]'] = $assignment_info['unlock_at'];
        }
        if (isset($assignment_info['due_at'])) {
            $data['assignment[due_at]'] = $assignment_info['due_at'];
        }
        if (isset($assignment_info['shown'])) {
            $data['assignment[published]'] = $assignment_info['shown'];
        }
        $data['assignment[hide_in_gradebook]'] = false;
        return $this->_doCurl($lms_access_token->access_token, 'PUT', $url, $data);
    }

    /**
     * @param int $course_id
     * @param int $assignment_id
     * @return array
     * @throws Exception
     */
    public function deleteAssignment(int $course_id, int $assignment_id): array
    {

        $lms_access_token = $this->_updateAccessToken();
        $url = "/api/v1/courses/$course_id/assignments/$assignment_id";

        return $this->_doCurl($lms_access_token->access_token, 'DELETE', $url);
    }

    /**
     * @param int $course_id
     * @return array
     * @throws Exception
     */
    public function getCourse(int $course_id): array
    {
        $lms_access_token = $this->_updateAccessToken();
        $url = "/api/v1/courses/$course_id";
        return $this->_doCurl($lms_access_token->access_token, 'GET', $url);
    }


    /**
     * @return array
     * @throws Exception
     */
    public function getCourses(): array
    {
        $lms_access_token = $this->_updateAccessToken();
        $url = "/api/v1/courses?enrollment_type=teacher&per_page=100";
        return $this->_doCurl($lms_access_token->access_token, 'GET', $url);
    }

    /**
     * @param int $course_id
     * @return array
     * @throws Exception
     */
    public function getEnrollments(int $course_id)
    {
        $lms_access_token = $this->_updateAccessToken();
        $url = "/api/v1/courses/$course_id/enrollments";
        return $this->_doCurl($lms_access_token->access_token, 'GET', $url);

    }


    /**
     * @param string $authorization_code
     * @param string $refresh_token
     * @return array
     */
    public function getAccessToken(string $authorization_code = '', string $refresh_token = ''): array
    {

        $api_key = $this->lti_registration->api_key;//from the developer board
        $api_secret = $this->lti_registration->api_secret;
        $result['type'] = 'error';

        $token_url = "{$this->lti_registration->auth_server}/login/oauth2/token";
        $callback_uri = request()->getSchemeAndHttpHost() . "/instructors/courses/lms/access-granted";

        if ($authorization_code) {
            $content = ['grant_type' => 'authorization_code', 'code' => $authorization_code];
        } else {
            // Refresh token is now passed in by the caller (already scoped to the correct
            // user + school) instead of being independently queried here by user_id alone,
            // which could previously grab the wrong school's refresh_token for users with
            // tokens across multiple Canvas instances.
            $content = ['grant_type' => 'refresh_token', 'refresh_token' => $refresh_token];
        }
        $content['redirect_uri'] = $callback_uri;

        $http = Http::withBasicAuth($api_key, $api_secret)
            ->withHeaders(['User-Agent' => 'ADAPT https://adapt.libretexts.org/ (adapt@libretexts.org)'])
            ->asForm();

        // Only skip SSL verification in local dev (e.g. self-signed certs on a
        // local/Docker Canvas instance). Staging and production always verify.
        if (app()->environment('local')) {
            $http = $http->withOptions(['verify' => false]);
        }

        try {
            $response = $http->post($token_url, $content);
        } catch (\Exception $e) {
            $result['message'] = 'We were not able to connect with Canvas: ' . $e->getMessage() . '.  Please contact us for assistance.';
            return $result;
        }

        $body = $response->json();

        if (is_null($body)) {
            $result['message'] = 'We were not able to obtain your access token.  Please contact us for assistance.';
        } elseif (isset($body['error'])) {
            $result['message'] = 'We were not able to connect with Canvas: ' . $body['error'] . '.  Please contact us for assistance.';
        } else {
            $result['type'] = 'success';
            $result['access_token'] = $body['access_token'];
            //this will happen the first time.  Then it's reused
            if (isset($body['refresh_token'])) {
                $result['refresh_token'] = $body['refresh_token'];
            }
        }
        return $result;
    }

    /**
     * @param string $access_token
     * @param string $type
     * @param string $url
     * @param array $data
     * @return array
     * @throws Exception
     */
    private function _doCurl(string $access_token, string $type, string $url, array $data = []): array
    {
        $response['type'] = 'error';

        $http = Http::withHeaders([
            'Accept' => 'application/json',
            'User-Agent' => 'ADAPT https://adapt.libretexts.org/ (adapt@libretexts.org)',
            'Authorization' => "Bearer $access_token",
        ]);

        $full_url = $this->_buildUrl($url);

        try {
            switch ($type) {
                case('GET'):
                    $api_response = $http->get($full_url);
                    break;
                case('POST'):
                    $api_response = $http->asForm()->post($full_url, $data);
                    break;
                case('PUT'):
                    $api_response = $http->asForm()->put($full_url, $data);
                    break;
                case('DELETE'):
                    $api_response = $http->delete($full_url, $data);
                    break;
                default:
                    throw new Exception("Not a valid type for the Canvas API cURL");
            }
        } catch (Exception $e) {
            $response['message'] = 'Connection issue: ' . $e->getMessage();
            return $response;
        }

        $original_result = $api_response->body();
        $result = json_decode($original_result); // decode as object, same shape as the old curl version

        if ($result === null) {
            $response['message'] = $original_result;
        } else if (is_object($result) && property_exists($result, 'errors')) {
            $curl_error = json_encode($result->errors);
            $response['message'] = "Canvas cURL error: $curl_error";
        } else {
            $response['type'] = 'success';
            $response['message'] = $result;
        }

        return $response;
    }

    /**
     * @param $url
     * @return string
     */
    private function _buildUrl($url): string
    {
        return $this->lti_registration->auth_server . $url;
    }

    /**
     * @param $name
     * @return string
     */
    public function addADAPT($name): string
    {
        return str_replace(' (ADAPT)', '', $name) . ' (ADAPT)';

    }

    /**
     * @param $course
     * @param $property
     * @return array
     * @throws Exception
     */
    public function updateCanvasAssignments($course, $property): array
    {
        $assignments = $course->assignments;
        $response['type'] = 'error';
        switch ($property) {
            case('points'):
                if (in_array(app()->environment(), ['dev', 'production'])) {
                    $ltiLaunch = new LtiLaunch();
                    $ltiGradePassback = new LtiGradePassback();
                    $assignment_ids = (new Assignment)->getAssignmentIds($assignments);
                    $total_points_by_assignment_id = (new Assignment)->getTotalPointsByAssignmentId($assignments, $assignment_ids);
                    foreach ($assignments as $assignment) {
                        $total_points = !$assignment->formative ? $total_points_by_assignment_id[$assignment->id] : 0;
                        $this->updateAssignment($course->lms_course_id,
                            $assignment->lms_assignment_id,
                            ['total_points' => $total_points]);
                        $lti_grade_passbacks = DB::table('lti_grade_passbacks')
                            ->where('assignment_id', $assignment->id)
                            ->where('created_at', '>=', Carbon::createFromDate(2023, 12, 15))
                            ->get();
                        foreach ($lti_grade_passbacks as $lti_grade_passback) {
                            $lti_grade_passback = DB::table('lti_grade_passbacks')->where('id', $lti_grade_passback->id)->first();
                            $lti_launch = $ltiLaunch->where('launch_id', $lti_grade_passback->launch_id)->first();
                            if ($lti_launch) {
                                $ltiGradePassback->passBackByUserIdAndAssignmentId($lti_grade_passback->score, $lti_launch);
                            }
                        }
                    }
                } else {
                    sleep(5);
                }
                DB::table('canvas_updates')->updateOrInsert(['course_id' => $course->id], ['updated_points' => 1, 'updated_at' => now()]);
                $response['type'] = 'success';
                $response['message'] = "The Canvas assignment points have been updated for $course->name.";
                break;
            case('everybodys'):
                if (in_array(app()->environment(), ['dev', 'production'])) {
                    foreach ($assignments as $assignment) {
                        $this->updateAssignment($course->lms_course_id,
                            $assignment->lms_assignment_id,
                            $assignment->getIsoUnlockAtDueAt([]));
                    }
                } else {
                    sleep(5);
                }
                DB::table('canvas_updates')->updateOrInsert(['course_id' => $course->id], ['updated_everybodys' => 1, 'updated_at' => now()]);
                $response['type'] = 'success';
                $response['message'] = "The Canvas dates have been updated for $course->name.";
                break;
            default:
                $response['message'] = "Error: $property is not a valid property.";
                break;
        }
        return $response;
    }
}
