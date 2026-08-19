<?php

namespace App;

use App\Exceptions\Handler;
use Exception;
use Illuminate\Database\Eloquent\Model;

class LmsAPI extends Model
{


    /**
     * @param object $lti_registration
     * @param int $user_id
     * @param $course_id
     * @param $assignment_id
     * @param array $data
     * @return array
     * @throws Exception
     */
    public function updateAssignment(object $lti_registration, int $user_id, $course_id, $assignment_id, array $data): array
    {
        switch ($lti_registration->iss) {
            case('https://canvas.instructure.com'):
            case('https://canvas.libretexts.org'):
            case('https://staging-canvas.libretexts.org'):
                $canvasAPI = new CanvasAPI($lti_registration, $user_id);
                $response = $canvasAPI->updateAssignment($course_id, $assignment_id, $data);
                break;
            default:
                throw new Exception(" $lti_registration->iss is not set up to update assignments through the LMS API.");

        }
        return $response;
    }

    /**
     * @param object $lti_registration
     * @param int $user_id
     * @param $course_id
     * @param $assignment_id
     * @return array
     * @throws Exception
     */
    public function deleteAssignment(object $lti_registration, int $user_id, $course_id, $assignment_id): array
    {
        switch ($lti_registration->iss) {
            case('https://canvas.instructure.com'):
            case('https://canvas.libretexts.org'):
            case('https://staging-canvas.libretexts.org'):
                $canvasAPI = new CanvasAPI($lti_registration, $user_id);
                $response = $canvasAPI->deleteAssignment($course_id, $assignment_id);
                break;
            default:
                throw new Exception("$lti_registration->iss is not set up to delete assignments through the LMS API.");

        }
        return $response;
    }


    /**
     * @param object $lti_registration
     * @param int $user_id
     * @param int $course_id
     * @param array $data
     * @return array
     * @throws Exception
     */
    public function createAssignment(object $lti_registration, int $user_id, int $course_id, array $data): array
    {
        switch ($lti_registration->iss) {
            case('https://canvas.instructure.com'):
            case('https://canvas.libretexts.org'):
            case('https://staging-canvas.libretexts.org'):
                $canvasAPI = new CanvasAPI($lti_registration, $user_id);
                $response = $canvasAPI->createAssignment($course_id, $data);
                break;
            default:
                throw new Exception("$lti_registration->iss is not set up to create assignments through the LMS API.");

        }
        return $response;
    }

    /**
     * @param object $lti_registration
     * @param int $user_id
     * @param $course_id
     * @return array
     * @throws Exception
     */
    public function getAssignments(object $lti_registration, int $user_id, $course_id): array
    {
        try {
            switch ($lti_registration->iss) {
                case('https://canvas.instructure.com'):
                case('https://canvas.libretexts.org'):
                case('https://staging-canvas.libretexts.org'):
                    $canvasAPI = new CanvasAPI($lti_registration, $user_id);
                    $response = $canvasAPI->getAssignments($course_id);
                    break;
                default:
                    throw new Exception("$lti_registration->iss is not set up to get assignments through the LMS API.");

            }
        } catch (Exception $e) {
            $response['type'] = 'error';
            $response['message'] = $e->getMessage();
            $h = new Handler(app());
            $h->report($e);
        }
        return $response;

    }

    /**
     * @param string $iss
     * @param $course_id
     * @return string
     * @throws Exception
     */
    public function getCourseUrl(string $iss, $course_id): string
    {
        switch ($iss) {
            case('https://canvas.instructure.com'):
            case('https://canvas.libretexts.org'):
            case('https://staging-canvas.libretexts.org'):
                $url = "$iss/courses/$course_id";
                break;
            default:
                throw new Exception("$iss is not set up to get a course URL through the LMS API.");

        }
        return $url;

    }

    /**
     * @throws Exception
     */
    public function getCourse(object $lti_registration, int $user_id, $course_id): array
    {
        try {
            switch ($lti_registration->iss) {
                case('https://canvas.instructure.com'):
                case('https://canvas.libretexts.org'):
                case('https://staging-canvas.libretexts.org'):
                    $canvasAPI = new CanvasAPI($lti_registration, $user_id);
                    $response = $canvasAPI->getCourse($course_id);
                    break;
                default:
                    throw new Exception("$lti_registration->iss is not set up to get a course through the LMS API.");

            }
            if ($response['type'] === 'success') {
                $response['course'] = $response['message'];
            }
        } catch (Exception $e) {
            $response['type'] = 'error';
            $response['message'] = $e->getMessage();
            $h = new Handler(app());
            $h->report($e);
        }
        return $response;
    }


    /**
     * @throws Exception
     */
    public function getCourses(object $lti_registration, int $user_id): array
    {
        try {
            switch ($lti_registration->iss) {
                case('https://canvas.instructure.com'):
                case('https://canvas.libretexts.org'):
                case('https://staging-canvas.libretexts.org'):
                    $canvasAPI = new CanvasAPI($lti_registration, $user_id);
                    $response = $canvasAPI->getCourses();
                    if ($response['type'] === 'success') {
                        $courses = $response['message'];
                        $message = [];
                        foreach ($courses as $course) {
                            if (property_exists($course, 'access_restricted_by_date') && $course->access_restricted_by_date) {
                                continue;
                            } else {
                                $message[] = $course;
                            }
                        }
                        $response['message'] = $message;
                    }
                    break;
                default:
                    throw new Exception("$lti_registration->iss is not set up to create courses through the LMS API.");

            }
            if ($response['type'] === 'success') {
                $response['courses'] = $response['message'];
            }
        } catch (Exception $e) {
            $response['type'] = 'error';
            $response['message'] = $e->getMessage();
            $h = new Handler(app());
            $h->report($e);
        }
        return $response;
    }

}
