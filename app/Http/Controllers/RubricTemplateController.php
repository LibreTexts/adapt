<?php

namespace App\Http\Controllers;

use App\Exceptions\Handler;
use App\Helpers\DynamicRubric;
use App\Http\Requests\StoreRubricRequest;
use App\Http\Requests\StoreRubricTemplateRequest;
use App\RubricTemplate;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class RubricTemplateController extends Controller
{
    /**
     * @param Request $request
     * @param RubricTemplate $rubricTemplate
     * @return array
     * @throws Exception
     */
    public function index(Request $request, RubricTemplate $rubricTemplate): array
    {

        try {
            $response['type'] = 'error';
            $rubric_templates = $rubricTemplate->where('user_id', $request->user()->id)->get();
            $response['type'] = 'success';
            $response['rubric_templates'] = $rubric_templates;
        } catch (Exception $e) {
            $h = new Handler(app());
            $h->report($e);
            $response['message'] = "We were not able to retrieve your rubric templates.  Please try again or contact support.";

        }
        return $response;

    }

    /**
     * @param StoreRubricTemplateRequest $request
     * @param RubricTemplate $rubricTemplate
     * @return array
     * @throws Exception
     */
    public function store(StoreRubricTemplateRequest $request, RubricTemplate $rubricTemplate): array
    {
        try {
            $response['type'] = 'error';
            $authorized = Gate::inspect('store', $rubricTemplate);
            if (!$authorized->allowed()) {
                $response['message'] = $authorized->message();
                return $response;
            }
            $data = $request->validated();
            //templates keep the scoring style but never item ids (loading a template always creates new items)
            $data['rubric'] = json_encode(DynamicRubric::templateRubric($data['rubric_items'], $request->rubric_shown, $data['scoring_style'] ?? null));
            unset($data['scoring_style']);

            $data['user_id'] = $request->user()->id;
            unset($data['rubric_items']);
            $rubricTemplate->create($data);
            $response['type'] = 'success';
            $response['message'] = "The rubric template has been created.";
        } catch (Exception $e) {
            $h = new Handler(app());
            $h->report($e);
            $response['message'] = "We were not able to save your rubric template.  Please try again or contact support.";

        }
        return $response;

    }

    /**
     * @param StoreRubricTemplateRequest $request
     * @param RubricTemplate $rubricTemplate
     * @return array
     * @throws Exception
     */
    public function update(StoreRubricTemplateRequest $request, RubricTemplate $rubricTemplate): array
    {
        try {
            $response['type'] = 'error';
            $authorized = Gate::inspect('update', $rubricTemplate);
            if (!$authorized->allowed()) {
                $response['message'] = $authorized->message();
                return $response;
            }
            $data = $request->validated();

            //templates keep the scoring style but never item ids (loading a template always creates new items)
            $data['rubric'] = json_encode(DynamicRubric::templateRubric($data['rubric_items'], $request->rubric_shown, $data['scoring_style'] ?? null));
            unset($data['scoring_style']);
            unset($data['rubric_items']);
            $rubricTemplate->update($data);
            $response['type'] = 'success';
            $response['message'] = "The rubric template <strong>$request->name</strong> has been updated.";
        } catch (Exception $e) {
            $h = new Handler(app());
            $h->report($e);
            $response['message'] = "We were not able to update your rubric template.  Please try again or contact support.";

        }
        return $response;

    }

    /**
     * @param RubricTemplate $rubricTemplate
     * @return array
     * @throws Exception
     */
    public function destroy(RubricTemplate $rubricTemplate): array
    {
        try {
            $response['type'] = 'error';
            $authorized = Gate::inspect('delete', $rubricTemplate);
            if (!$authorized->allowed()) {
                $response['message'] = $authorized->message();
                return $response;
            }
            $name = $rubricTemplate->name;
            $rubricTemplate->delete();
            $response['type'] = 'info';
            $response['message'] = "The rubric template <strong>$name</strong> has been deleted.";
        } catch (Exception $e) {
            $h = new Handler(app());
            $h->report($e);
            $response['message'] = "We were not able to delete your rubric template.  Please try again or contact support.";

        }
        return $response;

    }

    /**
     * @param RubricTemplate $rubricTemplate
     * @return array
     * @throws Exception
     */
    public function copy(RubricTemplate $rubricTemplate): array
    {
        try {
            $response['type'] = 'error';
            $authorized = Gate::inspect('copy', $rubricTemplate);
            if (!$authorized->allowed()) {
                $response['message'] = $authorized->message();
                return $response;
            }
            $rubric_template = $rubricTemplate->replicate();
            $name = $rubric_template->name;
            $rubric_template->name = $name . " copy";
            $rubric_template->save();
            $response['type'] = 'success';
            $response['message'] = "The rubric template <strong>$name</strong> has been copied.";
        } catch (Exception $e) {
            $h = new Handler(app());
            $h->report($e);
            $response['message'] = "We were not able to delete your rubric template.  Please try again or contact support.";

        }
        return $response;

    }

    /**
     * @param StoreRubricTemplateRequest $request
     * @return array
     * @throws Exception
     */
    public function validateRubricItems(StoreRubricTemplateRequest $request): array
    {

        try {
            $response['type'] = 'error';
            $request->validated();
            $response['type'] = 'success';
        } catch (Exception $e) {
            $h = new Handler(app());
            $h->report($e);
            $response['message'] = "We were not able to validate your rubric.  Please try again or contact support.";

        }
        return $response;


    }


}
