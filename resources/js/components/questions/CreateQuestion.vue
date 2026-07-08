<template>
  <div>
    <AllFormErrors :all-form-errors="allFormErrors" :modal-id="`modal-form-errors-questions-form-${questionsFormKey}`"/>
    <AllFormErrors :all-form-errors="allFormErrors" modal-id="modal-form-errors-discuss-it-text-form"/>
    <AllFormErrors :all-form-errors="allFormErrors"
                   modal-id="modal-form-errors-question-subject-chapter-section-errors"
    />
    <b-modal id="modal-confirm-delete-attachment"
             title="Confirm Remove Attachment"
    >
      <p>
        You are about to remove: <br><br><span class="text-center"><strong>{{
          attachmentToDelete.original_filename
        }}</strong></span><br><br> Please save your question to make this change permanent.
      </p>
      <template #modal-footer>
        <b-button
          size="sm"
          @click="$bvModal.hide('modal-confirm-delete-attachment')"
        >
          Cancel
        </b-button>
        <b-button
          size="sm"
          variant="danger"
          @click="$bvModal.hide('modal-confirm-delete-attachment');deleteAttachment();"
        >
          Remove
        </b-button>
      </template>
    </b-modal>
    <b-modal id="modal-confirm-no-open-ended-submission-with-no-auto-grading"
             title="Confirm No Open-ended Submission"
             no-close-on-esc
    >
      <p>
        You are creating a question with an HTML block but no open-ended submission and no auto-graded submission.
        Typically questions with only HTML will have some way for students to submit their work.
      </p>
      <p>Would you like to save the question as is?</p>
      <template #modal-footer>
        <b-button
          size="sm"
          @click="$bvModal.hide('modal-confirm-no-open-ended-submission-with-no-auto-grading')"
        >
          Cancel
        </b-button>
        <b-button
          size="sm"
          variant="primary"
          @click="$bvModal.hide('modal-confirm-no-open-ended-submission-with-no-auto-grading');checkedOpenEndedSubmissionType = true; initSaveQuestion()"
        >
          Save
        </b-button>
      </template>
    </b-modal>
    <b-modal id="modal-add-edit-question-subject-chapter-section"
             :title="`${capitalize(questionSubjectChapterSectionAction)} ${capitalize(questionSubjectChapterSectionToAddEditLevel)}`"
             no-close-on-backdrop
             size="lg"
    >
      <b-form-group
        label-cols-sm="2"
        label-cols-lg="1"
        label-for="level"
        label-align="center"
        label="Name"
      >
        <b-form-input v-model="questionSubjectChapterSectionForm.name"
                      required
                      :class="{ 'is-invalid': questionSubjectChapterSectionForm.errors.has('name')}"
                      @keydown="questionSubjectChapterSectionForm.errors.clear('name')"
        />
        <has-error :form="questionSubjectChapterSectionForm" field="name"/>
      </b-form-group>
      <template #modal-footer>
        <b-button
          size="sm"
          @click="$bvModal.hide('modal-add-edit-question-subject-chapter-section')"
        >
          Cancel
        </b-button>
        <b-button
          size="sm"
          variant="primary"
          @click="handleAddEditQuestionSubjectChapterSection"
        >
          Save
        </b-button>
      </template>
    </b-modal>
    <b-modal id="modal-confirm-update-structure"
             title="Confirm Update Structure"
             no-close-on-backdrop
    >
      <p>
        Please confirm that you would like to update the structure. By doing so, the Sketcher will be reset: your
        structure, any associated point values, and any feedback will also be deleted as well.
      </p>
      <template #modal-footer>
        <b-button
          size="sm"
          @click="$bvModal.hide('modal-confirm-update-structure')"
        >
          Cancel
        </b-button>
        <b-button
          size="sm"
          variant="danger"
          @click="updateStructure"
        >
          Update Structure
        </b-button>
      </template>
    </b-modal>
    <b-modal id="modal-submissions-exist-warning"
             title="Submissions Exist"
             size="lg"
             no-close-on-backdrop
             hide-header-close
    >
      <p>
        For some of your currently open assignments, students have <strong>already submitted work</strong>. If you
        choose this option,
        then their submissions <strong>will be erased</strong>.
      </p>
      <b-alert variant="danger" show>
        Once student submissions are erased, they cannot be retrieved.
      </b-alert>
      <template #modal-footer>
        <b-button
          variant="primary"
          size="sm"
          @click="$bvModal.hide('modal-submissions-exist-warning')"
        >
          I understand
        </b-button>
      </template>
    </b-modal>
    <b-modal id="modal-discuss-it-text"
             :title="activeQuestionMediaUpload.is_edit ? 'Add Text' : 'Edit Text'"
             size="xl"
             @hidden="activeQuestionMediaUpload = {}"
             @shown="updateModalToggleIndex('modal-discuss-it-text')"
    >
      <b-form-group
        label-cols-sm="3"
        label-cols-lg="2"
        label-for="description"
        label-size="sm"
        label-align="center"
      >
        <template v-slot:label>
          Description
          <QuestionCircleTooltip :id="'discuss-it-description-tooltip'"/>
          <b-tooltip target="discuss-it-description-tooltip"
                     delay="250"
                     triggers="hover focus"
          >
            A short description, not viewable by the student, but used as an identifier in the list of associated
            media
            uploads for this question.
          </b-tooltip>
        </template>
        <b-form-input v-model="discussItTextForm.description"
                      required
                      style="width:300px"
                      size="sm"
                      :class="{ 'is-invalid': discussItTextForm.errors.has('description')}"
                      @keydown="discussItTextForm.errors.clear('description')"
        />
        <has-error :form="discussItTextForm" field="description"/>
      </b-form-group>
      <ckeditor
        id="discuss_it_text"
        ref="discuss_it_text"
        v-model="discussItTextForm.text"
        tabindex="0"
        required
        :config="richEditorConfig"
        :class="{ 'is-invalid': discussItTextForm.errors.has('text')}"
        class="mb-2"
        @namespaceloaded="onCKEditorNamespaceLoaded"
        @ready="handleFixCKEditor()"
        @focus="ckeditorKeyDown=true"
        @keydown="discussItTextForm.errors.clear('text')"
      />
      <has-error :form="discussItTextForm" field="text"/>
      <template #modal-footer>
        <b-button
          variant="secondary"
          size="sm"
          class="Cancel"
          @click="$bvModal.hide('modal-discuss-it-text')"
        >
          Cancel
        </b-button>
        <b-button
          variant="primary"
          size="sm"
          class="float-right"
          @click="saveDiscussItText"
        >
          Save
        </b-button>
      </template>
    </b-modal>
    <b-modal id="modal-compare-revisions"
             title="Compare Revisions"
             size="xl"
             scrollable
             hide-footer
             no-close-on-backdrop
             @shown="increaseModalSize"
    >
      <b-form-group>
        <b-form-row>
          <b-form-select v-model="revision1Id"
                         style="width:500px"
                         size="sm"
                         :options="revisionOptions"
                         class="mt-2 mr-2"
                         @change="compareRevisions"
          />
          <b-form-select v-model="revision2Id"
                         style="width:500px"
                         size="sm"
                         :options="revisionOptions"
                         class="mt-2 mr-2"
                         @change="compareRevisions"
          />
        </b-form-row>
      </b-form-group>
      <QuestionRevisionDifferences :key="`question-revision-differences-${questionRevisionDifferencesKey}`"
                                   :revision1="revision1"
                                   :revision2="revision2"
                                   :diffs-shown="diffsShown"
                                   :math-jax-rendered="mathJaxRendered"
                                   @reloadQuestionRevisionDifferences="reloadQuestionRevisionDifferences"
      />
    </b-modal>
    <b-modal id="modal-save-and-propagate"
             title="Save and Propagate"
             size="lg"
    >
      <b-form-group label="Reason for Edit (Optional)">
        <b-textarea v-model="questionForm.reason_for_edit"
                    style="width:100%"
                    rows="5"
        />
      </b-form-group>
      <b-form-checkbox
        id="checkbox-1"
        v-model="questionForm.changes_are_topical"
        name="changes_made_are_topical"
        :value="true"
        :unchecked-value="false"
      >
        The changes I made are topical in nature.
      </b-form-checkbox>

      <template #modal-footer>
        <b-button
          variant="secondary"
          size="sm"
          class="Cancel"
          @click="$bvModal.hide('modal-save-and-propagate')"
        >
          Cancel
        </b-button>
        <b-button
          variant="primary"
          size="sm"
          class="float-right"
          @click="submitSaveAndPropagate"
        >
          Submit
        </b-button>
      </template>
    </b-modal>
    <b-modal id="modal-reason-for-edit"
             title="Reason for Edit"
             size="lg"
             no-close-on-backdrop
             @shown="questionForm.automatically_update_revision = ''"
    >
      <b-form-group
        v-if="powerUser"
        id="revision_action"
        label-cols-sm="3"
        label-cols-lg="2"
        label="Revision Action"
      >
        <b-form-radio-group
          v-model="revisionAction"
          class="mt-2"
        >
          <b-form-radio name="revision_action" value="notify">
            Notify
          </b-form-radio>

          <b-form-radio name="revision_action" value="propagate">
            Propagate
          </b-form-radio>
        </b-form-radio-group>
      </b-form-group>
      <div v-if="revisionAction === 'notify' || (revisionAction === 'propagate' && !powerUser)">
        <p>
          Reason for editing the question:
        </p>
        <b-textarea v-model="questionForm.reason_for_edit"
                    style="width:100%"
                    rows="5"
                    :class="{ 'is-invalid': questionForm.errors.has('reason_for_edit')}"
                    @keydown="questionForm.errors.clear('reason_for_edit')"
        />
        <has-error :form="questionForm" field="reason_for_edit"/>

        <hr class="pt-2 pb-2">
      </div>
      <div v-if="revisionAction === 'notify'">
        <b-form-group
          id="automatically_update_revision"
          label-cols-sm="5"
          label-cols-lg="4"
          label="For my own current assignments:"
        >
          <b-form-row>
            <b-form-radio-group
              v-model="questionForm.automatically_update_revision"
              stacked
              @change="checkForStudentSubmissions($event)"
            >
              <b-form-radio name="automatically_update_revision" value="1">
                Automatically update the question
              </b-form-radio>
              <b-form-radio name="automatically_update_revision" value="0">
                Do not automatically update the question
              </b-form-radio>
            </b-form-radio-group>
          </b-form-row>
          <ErrorMessage :message="questionForm.errors.get('automatically_update_revision')"/>
        </b-form-group>
      </div>
      <div v-if="revisionAction === 'propagate'">
        <div v-if="!powerUser">
          <b-alert show variant="info">
            Since your edits were purely topical in nature, all instructors will automatically receive your updated
            question.
          </b-alert>
        </div>
        <div v-if="powerUser">
          <b-form-checkbox
            id="checkbox-1"
            v-model="questionForm.changes_are_topical"
            name="changes_made_are_topical"
            :value="true"
            :unchecked-value="false"
          >
            The changes I made are topical in nature.
          </b-form-checkbox>
          <ErrorMessage :message="questionForm.errors.get('changes_are_topical')"/>
        </div>
      </div>
      <template #modal-footer>
        <b-button
          variant="secondary"
          size="sm"
          @click="$bvModal.hide('modal-reason-for-edit')"
        >
          Cancel
        </b-button>
        <b-button
          variant="primary"
          size="sm"
          class="float-right"
          :disabled="!revisionAction"
          @click="saveQuestion()"
        >
          Submit
        </b-button>
      </template>
    </b-modal>
    <b-modal
      id="modal-confirm-delete-webwork-attachment"
      :title="`Delete ${webworkAttachmentToDelete.filename}`"
    >
      Please confirm whether you would like to delete {{ webworkAttachmentToDelete.filename }}. If you delete this
      file,
      be sure to update your WeBWork code as well.
      <template #modal-footer>
        <b-button
          variant="secondary"
          size="sm"
          class="Cancel"
          @click="$bvModal.hide('modal-confirm-delete-webwork-attachment')"
        >
          Cancel
        </b-button>
        <b-button
          variant="danger"
          size="sm"
          class="float-right"
          @click="deleteWebworkAttachment()"
        >
          Delete Attachment
        </b-button>
      </template>
    </b-modal>

    <b-modal
      id="modal-webwork-image-options"
      no-close-on-backdrop
      size="lg"
      :title="`Create webWork code for ${webworkImageOptions.filename}`"
    >
      <p>
        ADAPT can automatically create the necessary WeBWork to create <a
        href="https://webwork.maa.org/wiki/StaticImages" target="_blank"
      >static images</a>. All parameters are optional.
      </p>
      <b-card header-html="<h5>Resize Image</h5>" class="mb-2">
        <template #header>
          <div>
            <h5 class="mb-0">
              Resize Image
            </h5>
          </div>
          <div>
            Original dimensions: {{
              webworkImageOptions.width
            }}px by {{ webworkImageOptions.height }}px
            <b-button size="sm" variant="info"
                      @click="resetImageResize"
            >
              Reset
            </b-button>
          </div>
        </template>

        <b-form-radio-group
          id="resize-by"
          v-model="resizeImageBy"
          label="By"
          @input="initImageSize(resizeImageBy)"
        >
          <b-form-radio name="resize_image_by" value="percentage">
            Percentage
          </b-form-radio>
          <b-form-radio name="resize_image_by" value="pixels">
            Pixels
          </b-form-radio>
        </b-form-radio-group>
        <b-form-checkbox
          id="aspect-ratio"
          v-model="maintainAspectRatio"
          name="aspect-ratio"
          :value="true"
          :unchecked-value="false"
        >
          Maintain Aspect Ratio
        </b-form-checkbox>
        <b-form-group
          label-cols-sm="2"
          label-cols-lg="1"
          label-for="width"
          label="Width"
        >
          <b-form-row>
            <b-input-group size="sm" :append="resizeImageBy === 'pixels' ? 'pixels' : '%'" style="width:175px">
              <b-form-input
                id="width"
                v-model="resizeWidth"
                type="text"
                @focus="initResizeWidth = resizeWidth"
                @blur="updateResizeHeight"
              />
            </b-input-group>
          </b-form-row>
        </b-form-group>
        <b-form-group
          label-cols-sm="2"
          label-cols-lg="1"
          label-for="height"
          label="Height"
        >
          <b-form-row>
            <b-input-group size="sm" :append="resizeImageBy === 'pixels' ? 'pixels' : '%'" style="width:175px">
              <b-form-input
                id="height"
                v-model="resizeHeight"
                type="text"
                @focus="initResizeHeight = resizeHeight"
                @blur="updateResizeWidth"
              />
            </b-input-group>
          </b-form-row>
        </b-form-group>
      </b-card>
      <b-form-group
        label-cols-sm="2"
        label-cols-lg="1"
        label-for="alt-text"
        label="Alt Text"
      >
        <b-form-row>
          <b-form-input
            id="alt_text"
            v-model="webworkImageOptions.alt_text"
            size="sm"
            type="text"
          />
        </b-form-row>
      </b-form-group>
      <template #modal-footer>
        <b-button
          variant="secondary"
          size="sm"
          class="Cancel"
          @click="$bvModal.hide('modal-webwork-image-options')"
        >
          Cancel
        </b-button>
        <b-button
          variant="primary"
          size="sm"
          class="float-right"
          @click="copyWebworkImageCode()"
        >
          Create Code
        </b-button>
      </template>
    </b-modal>
    <b-modal
      id="modal-clone-history"
      size="lg"
      no-close-on-backdrop
    >
      <template #modal-header>
        <div class="modal-header" style="width:100%;border:none;padding:0px">
          <h2 class="h5 modal-title">
            ADAPT ID <span id="clone-history-question-id">{{ copyHistoryQuestionId }}</span>
            <span class="text-muted" @click="doCopy('clone-history-question-id')"><font-awesome-icon
              :icon="copyIcon"
            /></span>
          </h2>
          <button type="button" aria-label="Close" class="close" @click="$bvModal.hide('modal-clone-history')">
            ×
          </button>
        </div>
      </template>
      <ViewQuestions :key="`view-clone-history-${copyHistoryQuestionId}`"
                     :question-ids-to-view="[copyHistoryQuestionId]"
                     :show-solutions="true"
      />
      <template #modal-footer>
        <b-button
          variant="primary"
          size="sm"
          class="float-right"
          @click="$bvModal.hide('modal-clone-history')"
        >
          OK
        </b-button>
      </template>
    </b-modal>
    <div v-if="!questionExistsInAnotherInstructorsAssignment && questionExistsInOwnAssignment">
      <b-alert :show="true" class="font-weight-bold">
        Warning: You are editing a question which already exists in one of your assignments.
      </b-alert>
    </div>
    <b-modal
      id="modal-current-question-editor"
      title="Question Currently Being Edited"
    >
      <p>{{ currentQuestionEditor }}</p>
      <template #modal-footer>
        <b-button
          variant="primary"
          size="sm"
          class="float-right"
          @click="$bvModal.hide('modal-current-question-editor')"
        >
          Got it!
        </b-button>
      </template>
    </b-modal>
    <b-modal
      id="modal-img-needs-alt"
      title="Image missing alternative text"
      hide-footer
    >
      <p>
        The following image is missing alternative text, which is descriptive text to help with accessibility issues.
        After closing this
        window, please locate the image, right-click, then click on Image Properties and add alternative text.
      </p>
      <img :src="imgNeedsAltSrc" alt="missing alternative text">
    </b-modal>
    <b-modal
      :id="`modal-confirm-delete-qti-${modalId}`"
      title="Confirm reset Native technology"
    >
      Hiding this area will delete the information associated with the Native technology. Are you sure you would like
      to
      do this?
      <template #modal-footer>
        <b-button
          size="sm"
          class="float-right"
          @click="$bvModal.hide(`modal-confirm-delete-qti-${modalId}`)"
        >
          Cancel
        </b-button>
        <b-button
          variant="primary"
          size="sm"
          class="float-right"
          @click="deleteQtiTechnology()"
        >
          Delete
        </b-button>
      </template>
    </b-modal>
    <b-modal
      :id="modalId"
      title="Preview Question"
      ok-title="OK"
      size="lg"
      ok-only
    >
      <div v-if="questionForm.technology === 'qti'">
        <b-button
          v-if="questionForm.technology === 'qti' && !['discuss_it','forge','flashcard'].includes(qtiQuestionType)"
          size="sm"
          variant="primary"
          @click="getQtiAnswerJson()"
        >
          View {{ questionForm.solution_html ? 'Solution' : 'Answer' }}
        </b-button>
        <QtiJsonQuestionViewer
          :key="`qti-json-question-viewer-${qtiJsonQuestionViewerKey}`"
          :qti-json="showQtiAnswer ? qtiAnswerJson : JSON.stringify(qtiJson)"
          :show-qti-answer="showQtiAnswer"
          :show-submit="false"
          :show-response-feedback="false"
          :preview-or-solution="showQtiAnswer"
          :previewing-question="previewingQuestion"
        />
        <div v-if="showQtiAnswer && questionForm.solution_html" v-html="questionForm.solution_html"/>
      </div>
      <ViewQuestions v-if="questionForm.technology !== 'qti'"
                     :key="questionToViewKey"
                     :question-to-view="questionToView"
      />
      <template #modal-footer>
        <b-button
          variant="primary"
          size="sm"
          class="float-right"
          @click="$bvModal.hide(modalId)"
        >
          OK
        </b-button>
      </template>
    </b-modal>
    <span ref="top-of-form"/>
    <div v-if="fullyMounted" v-show="false" id="from-sketcher-component">
      <Sketcher :error-message="questionForm.errors.get(`solution_structure`)"
                :solution-structure="solutionStructure"
                :init-reload="initSketcherReload"
                :type="sketcherType"
      />
    </div>
    <b-tabs id="question-editor-tabs"
            v-model="activeTabIndex"
            content-class="mt-3"
            filled
            active-nav-item-class="font-weight-bold bg-primary"
    >
      <b-tab id="properties"
             ref="properties"
             title="Properties"
             :title-link-class="getTabClass('properties')"

             active
      >
        <b-card border-variant="primary"
                class="mb-3"
        >
          <b-alert :show="isWebworkDownloadOnly">
            You do not have editing rights to this question. However, you may view any aspect of the question. In
            addition, you may export
            the webWork code, which can be found under the <a style="cursor: pointer;"
                                                              @click.prevent="activeTabIndex=1"
          >Primary Content</a> tab.
          </b-alert>
          <p>
            The question properties help us to organize the
            questions within ADAPT for searchability and also help
            us to provide accurate authorship and license information.
          </p>
          <p>
            <RequiredText/>
          </p>
          <b-form-group
            v-if="questionForm.clone_history && questionForm.clone_history.length"
            label-cols-sm="3"
            label-cols-lg="2"
          >
            <template v-slot:label>
              Clone History
              <QuestionCircleTooltip :id="'clone-history-tooltip'"/>
              <b-tooltip target="clone-history-tooltip"
                         delay="250"
                         triggers="hover focus"
              >
                You can view the complete clone history of this question if it was created as a clone of another
                question
                or series of questions.
              </b-tooltip>
            </template>
            <b-form-row class="pt-2">
              <span v-for="(questionId, index) in questionForm.clone_history"
                    :key="`view-clone-history-${index}`"
              >
                <a href="" @click.prevent="copyHistoryQuestionId=questionId;$bvModal.show('modal-clone-history')">{{
                    questionId
                  }}</a>
                <span v-if="questionForm.clone_history.length > 1 && index !== questionForm.clone_history.length-1"
                >-></span>
              </span>
            </b-form-row>
          </b-form-group>
          <b-modal id="modal-framework-aligner"
                   title="Framework Alignment"
                   size="lg"
                   no-close-on-backdrop
          >
            <FrameworkAligner :key="`framework-aligner-key-${isEdit ? +questionToEdit.id : 0}`"
                              :question-id="isEdit ? +questionToEdit.id : 0"
                              :framework-item-sync-question="frameworkItemSyncQuestion"
                              :is-create-question="true"
                              @setFrameworkItemSyncQuestion="setFrameworkItemSyncQuestion"
            />

            <template #modal-footer>
              <b-button
                variant="primary"
                size="sm"
                class="float-right"
                @click="$bvModal.hide('modal-framework-aligner')"
              >
                OK
              </b-button>
            </template>
          </b-modal>
          <b-form-group
            v-if="revisionOptions.length"
            label-cols-sm="3"
            label-cols-lg="2"
            label-for="revision"
            label="Revision*"
          >
            <b-form-row>
              <b-form-select v-model="revision"
                             style="width:500px"
                             size="sm"
                             :options="revisionOptions"
                             class="mt-2 mr-2"
                             @change="setNewQuestionToEdit"
              />
              <b-button size="sm"
                        variant="outline-primary"
                        style="height:30px;margin-top:8px"
                        @click="initCompareRevisions"
              >
                Compare Revisions
              </b-button>
            </b-form-row>
          </b-form-group>
          <b-form-group
            label-cols-sm="3"
            label-cols-lg="2"
            label-for="title"
            label="Title*"
          >
            <b-form-row>
              <b-form-input
                id="title"
                v-model="questionForm.title"
                size="sm"
                type="text"
                required
                :class="{ 'is-invalid': questionForm.errors.has('title') }"
                class="mt-2"
                @keydown="questionForm.errors.clear('title')"
              />
              <has-error :form="questionForm" field="title"/>
            </b-form-row>
          </b-form-group>
          <b-form-group
            label-cols-sm="3"
            label-cols-lg="2"
            label-for="description"
          >
            <template v-slot:label>
              Description
              <QuestionCircleTooltip :id="'description-tooltip'"/>
              <b-tooltip target="description-tooltip"
                         delay="250"
                         triggers="hover focus"
              >
                An optional short description of the question. This description is not viewable by students but is
                viewable
                in
                search
                results.
              </b-tooltip>
            </template>
            <b-form-row>
              <b-form-textarea
                id="title"
                v-model="questionForm.description"
                :min-rows="2"
                required
                class="mt-2"
              />
            </b-form-row>
          </b-form-group>
          <div>
            <b-form-group
              label-cols-sm="3"
              label-cols-lg="2"
              label-for="question_type"
              :label="isEdit ? 'Question Type' : 'Question Type*'"
            >
              <b-form-row>
                <b-form-radio-group
                  id="question_type"
                  v-model="questionForm.question_type"
                  stacked
                  :aria-required="!isEdit"
                  @change="switchingType = true;resetQuestionForm($event)"
                >
                  <b-form-radio name="question_type" value="assessment">
                    Question
                    <QuestionCircleTooltip :id="'assessment-question-type-tooltip'"/>
                    <b-tooltip target="assessment-question-type-tooltip"
                               delay="250"
                               triggers="hover focus"
                    >
                      Questions can be used within assignments. In addition, if they are purely auto-graded,
                      they can be used as root nodes in Learning Trees. Regardless of whether they have an auto-graded
                      technology, questions can be used in non-root nodes of
                      Learning Trees.
                    </b-tooltip>
                  </b-form-radio>
                  <b-form-radio name="question_type" value="exposition">
                    Exposition (use in Learning Trees only)
                    <QuestionCircleTooltip :id="'exposition-question-type-tooltip'"/>
                    <b-tooltip target="exposition-question-type-tooltip"
                               delay="250"
                               triggers="hover focus"
                    >
                      An Exposition consists of source (text, video, simulation, any other HTML) without an
                      auto-graded
                      component. They can be used in any of the non-root
                      nodes within Learning Trees.
                    </b-tooltip>
                  </b-form-radio>
                </b-form-radio-group>
              </b-form-row>
            </b-form-group>
          </div>

          <div>
            <b-form-group
              label-cols-sm="3"
              label-cols-lg="2"
              label-for="public"
            >
              <template v-slot:label>
                Public*
                <QuestionCircleTooltip :id="'public-question-tooltip'"/>
                <b-tooltip target="public-question-tooltip"
                           delay="250"
                           triggers="hover focus"
                >
                  Questions that are public can be used by any instructor. Questions that are not public are only
                  accessible
                  by you.
                </b-tooltip>
              </template>
              <b-form-row class="mt-2">
                <b-form-radio-group
                  id="public"
                  v-model="questionForm.public"
                >
                  <b-form-radio name="public" value="1">
                    Yes
                  </b-form-radio>
                  <b-form-radio name="public" value="0">
                    No
                  </b-form-radio>
                </b-form-radio-group>
              </b-form-row>
            </b-form-group>
          </div>
          <b-form-group
            v-if="isAdmin && isEdit"
            label-cols-sm="3"
            label-cols-lg="2"
            label-for="question_owner"
            label="Owner*"
          >
            <b-form-row>
              <autocomplete
                ref="ownerSearch"
                style="width:400px"
                :search="searchByOwner"
                :default-value="questionToEdit.question_editor_name"
                inline
                @submit="onOwnerSelect"
              />
            </b-form-row>
          </b-form-group>
          <b-form-group
            label-cols-sm="3"
            label-cols-lg="2"
            label-for="folder"
            label="Folder*"
          >
            <b-form-row>
    <span v-show="!showFolderOptions" class="mt-2">
      The folder is set by the question owner ({{ questionForm.question_editor_name }}).
    </span>
              <span v-show="showFolderOptions">
      <span v-if="isAdmin && isEdit">
        <b-form-select
          v-model="questionForm.folder_id"
          :options="ownerFolderOptions"
          size="sm"
          style="width:400px"
          class="mt-2"
        />
      </span>
      <span v-else>
        <SavedQuestionsFolders
          ref="savedQuestionsFolders1"
          :key="`saved-questions-folders-key-${savedQuestionsFolderKey}-${questionForm.folder_id}`"
          class="mt-2"
          :type="'my_questions'"
          :init-saved-questions-folder="questionForm.folder_id"
          :create-modal-add-saved-questions-folder="true"
          :folder-to-choose-from="'My Questions'"
          :question-source-is-my-favorites="false"
          @reloadSavedQuestionsFolders="reloadCreateQuestionSavedQuestionsFolders"
          @savedQuestionsFolderSet="setMyCoursesFolder"
        />
      </span>
    </span>
            </b-form-row>
            <ErrorMessage v-if="questionForm.errors.get('folder_id')"
                          :message="questionForm.errors.get('folder_id')"
            />
          </b-form-group>
          <div>
            <b-form-group
              label-cols-sm="3"
              label-cols-lg="2"
              label-for="author"
              label="Author(s)*"
            >
              <b-form-row>
                <b-form-input
                  id="author"
                  v-model="questionForm.author"
                  size="sm"
                  type="text"
                  :class="{ 'is-invalid': questionForm.errors.has('author') }"
                  class="mt-2"
                  @keydown="questionForm.errors.clear('author')"
                />
                <has-error :form="questionForm" field="author"/>
              </b-form-row>
            </b-form-group>
          </div>

          <b-form-group
            label-cols-sm="3"
            label-cols-lg="2"
            label-for="license"
            label="License*"
          >
            <b-form-row>
              <b-col cols="12" md="6" lg="4" class="m-0 p-0">
                <b-form-select v-model="questionForm.license"
                               title="license"
                               size="sm"
                               class="mt-2 mr-2"
                               :class="{ 'is-invalid': questionForm.errors.has('license') }"
                               :options="licenseOptions"
                               @change="questionForm.errors.clear('license');questionForm.license_version = updateLicenseVersions(questionForm.license)"
                />
                <has-error :form="questionForm" field="license"/>
              </b-col>
            </b-form-row>
          </b-form-group>
          <b-form-group
            v-if="licenseVersionOptions.length"
            label-cols-sm="3"
            label-cols-lg="2"
            label-for="license_version"
            label="License Version*"
          >
            <b-form-row>
              <b-form-select v-model="questionForm.license_version"
                             style="width:100px"
                             title="license version"
                             required
                             size="sm"
                             class="mt-2"
                             :options="licenseVersionOptions"
              />
            </b-form-row>
          </b-form-group>

          <div>
            <b-form-group
              label-cols-sm="3"
              label-cols-lg="2"
              label-for="source_url"
            >
              <template v-slot:label>
                Source URL*
                <QuestionCircleTooltip id="source_url-tooltip"/>
                <b-tooltip target="source_url-tooltip"
                           delay="250"
                           triggers="hover focus"
                >
                  URL where the question was created
                </b-tooltip>
              </template>
              <b-form-row>
                <b-form-input
                  id="source_url"
                  v-model="questionForm.source_url"
                  size="sm"
                  type="text"
                  :class="{ 'is-invalid': questionForm.errors.has('source_url') }"
                  class="mt-2"
                  @keydown="questionForm.errors.clear('source_url')"
                />
                <has-error :form="questionForm" field="source_url"/>
              </b-form-row>
            </b-form-group>
            <b-form-group
              label-cols-sm="3"
              label-cols-lg="2"
              label-for="tags"
              label="Tags"
            >
              <b-form-row class="mt-2">
                <b-form-input
                  id="tags"
                  v-model="tag"
                  style="width:200px"
                  type="text"
                  class="mr-2"
                  size="sm"
                />
                <b-button variant="outline-primary" size="sm" @click="addTag()">
                  Add Tag
                </b-button>
              </b-form-row>
              <div class="d-flex flex-row">
                <span v-for="chosenTag in questionForm.tags" :key="chosenTag" class="mt-2">
                  <b-button size="sm"
                            variant="secondary"
                            class="mr-2"
                            style="line-height:.8"
                            @click="removeTag(chosenTag)"
                  ><span v-html="chosenTag"/> x</b-button>
                </span>
              </div>
            </b-form-group>
            <b-form-group
              label-for="framework_alignment"
              label-cols-sm="3"
              label-cols-lg="2"
              label="Framework Alignment"
            >
              <div class="mt-1">
                <b-button size="sm" variant="outline-primary" @click="$bvModal.show('modal-framework-aligner');">
                  Update
                </b-button>
              </div>
            </b-form-group>
            <span v-if="frameworkItemSyncQuestion.descriptors.length">
              <span v-for="(descriptor, descriptorsIndex) in frameworkItemSyncQuestion.descriptors"
                    :key="`framework-item-sync-questions-descriptors-${descriptorsIndex}`"
                    class="mr-2"
              >
                <b-button size="sm"
                          variant="secondary"
                          style="line-height:.8"
                          @click="removeFrameworkItemSyncQuestion('descriptors',descriptor.id)"
                >{{
                    descriptor.text
                  }} x
                </b-button>
              </span>
            </span>
            <span v-if="frameworkItemSyncQuestion.levels.length">
              <span v-for="(level, levelsIndex) in frameworkItemSyncQuestion.levels"
                    :key="`framework-item-sync-questions-levels-${levelsIndex}`"
                    class="mr-2"
              >
                <b-button size="sm"
                          variant="secondary"
                          style="line-height:.8"
                          @click="removeFrameworkItemSyncQuestion('levels',level.id)"
                >{{
                    level.text
                  }} x
                </b-button>
              </span>
            </span>
            <b-form-group
              v-show="false"
              key="learning_outcome"
              label-for="learning_outcome"
              label-cols-sm="3"
              label-cols-lg="2"
            >
              <template v-slot:label>
                Learning Outcome
                <QuestionCircleTooltip :id="'learning-outcome-tooltip'"/>
                <b-tooltip target="learning-outcome-tooltip"
                           delay="250"
                           triggers="hover focus"
                >
                  Over time, we will be adding new learning outcome frameworks for different subjects. If you are
                  aware
                  of a learning outcome framework and your subject is not shown here, please contact us with the
                  source
                  of
                  the framework.
                </b-tooltip>
              </template>
              <b-form-row class="mt-2">
                <b-form-select id="learning_outcome"
                               v-model="subject"
                               style="width:150px"
                               size="sm"
                               class="mr-2"
                               :options="subjectOptions"
                               @change="updateLearningOutcomes($event)"
                />
                <v-select :key="`subject-${subject}`"
                          v-model="learningOutcome"
                          style="width:520px"
                          placeholder="Choose a learning outcome"
                          :options="learningOutcomeOptions.filter(learningOutcomeOption => !questionForm.learning_outcomes.includes(learningOutcomeOption.id))"
                          class="mb-2"
                          @input="addLearningOutcome(learningOutcome)"
                />
              </b-form-row>
              <div v-for="(chosenLearningOutcome, index) in questionForm.learning_outcomes"
                   :key="`chosen-learning-outcome-${index}`"
                   class="mt-2"
              >
                <b-button size="sm" variant="secondary" class="mr-2"
                          @click="removeLearningOutcome(chosenLearningOutcome)"
                >
                  {{
                    //labels are brought in if it's an edited question otherwise it's done on the fly
                    chosenLearningOutcome.label ? chosenLearningOutcome.label :
                      getLearningOutcomeLabel(chosenLearningOutcome)
                  }} x
                </b-button>
              </div>
            </b-form-group>
            <b-form-group
              label-for="subject"
              label-cols-sm="3"
              label-cols-lg="2"
              label="Subject"
            >
              <b-form-select v-model="questionForm.question_subject_id"
                             :options="questionSubjectIdOptions"
                             size="sm"
                             style="width:400px"
                             @change="questionForm.question_chapter_id = null; questionForm.question_section_id=null;getQuestionChapterIdOptions(questionForm.question_subject_id)"
              />
              <b-button size="sm"
                        variant="outline-info"
                        :disbled="questionForm.question_subject_id === null"
                        @click="initAddEditDeleteQuestionSubjectChapterSection('edit','subject')"
              >
                Edit
              </b-button>
              <b-button size="sm" variant="outline-primary"
                        @click="initAddEditDeleteQuestionSubjectChapterSection('add','subject')"
              >
                Add
              </b-button>
            </b-form-group>
            <b-form-group
              label-for="chapter"
              label-cols-sm="3"
              label-cols-lg="2"
              label="Chapter"
            >
              <b-form-select v-model="questionForm.question_chapter_id"
                             :options="questionChapterIdOptions"
                             size="sm"
                             style="width:400px"
                             :disabled="questionForm.question_subject_id === null || questionChapterIdOptions.length === 1"
                             @change="questionForm.question_section_id = null;getQuestionSectionIdOptions(questionForm.question_chapter_id)"
              />
              <b-button size="sm"
                        variant="outline-info"
                        :disabled="questionForm.question_chapter_id === null"
                        @click="initAddEditDeleteQuestionSubjectChapterSection('edit','chapter')"
              >
                Edit
              </b-button>
              <b-button size="sm" variant="outline-primary"
                        :disabled="questionForm.question_subject_id === null"
                        @click="initAddEditDeleteQuestionSubjectChapterSection('add','chapter')"
              >
                Add
              </b-button>
            </b-form-group>
            <b-form-group
              label-for="section"
              label-cols-sm="3"
              label-cols-lg="2"
              label="Section"
            >
              <b-form-select v-model="questionForm.question_section_id"
                             :options="questionSectionIdOptions"
                             :disabled="questionForm.question_chapter_id === null || questionSectionIdOptions.length === 1"
                             size="sm"
                             style="width:400px"
              />
              <b-button size="sm"
                        variant="outline-info"
                        :disabled="questionForm.question_section_id === null"
                        @click="initAddEditDeleteQuestionSubjectChapterSection('edit','section')"
              >
                Edit
              </b-button>
              <b-button size="sm"
                        variant="outline-primary"
                        :disabled="questionForm.question_chapter_id === null"
                        @click="initAddEditDeleteQuestionSubjectChapterSection('add','section')"
              >
                Add
              </b-button>
            </b-form-group>
          </div>
        </b-card>
      </b-tab>

      <b-tab id="primary-content"
             ref="primary-content"
             title="Primary Content"
             :title-link-class="getTabClass('primary-content')"
      >
        <b-card border-variant="primary"
                class="mb-3"
        >
          <p>
            Questions can consist of either pure HTML (text-based question), an auto-graded technology for automatic
            scoring, or
            may also consist of both types. Though the combined type is less common, it provides a way to incorporate
            additional resources
            such as video or other embedded media to complement the auto-graded portion of the question.
          </p>

          <p>Both blocks are rendered in problem to students.</p>

          <div class="mb-2">
            <b-button variant="primary"
                      size="sm"
                      @click="triggerFileDialog"
            >
              Upload Attachment
            </b-button>
            <QuestionCircleTooltip :id="'attachments-tooltip'"/>
            <b-tooltip target="attachments-tooltip"
                       delay="250"
                       triggers="hover focus"
            >
              You can attach files to your question that can then be downloaded by your students.
            </b-tooltip>
            <b-progress v-if="preSignedURL" max="100" class="mt-2 mb-3">
              <b-progress-bar :value="uploadProgress" :label="`${Number(uploadProgress).toFixed(0)}%`" show-progress
                              animated
              />
            </b-progress>
            <!-- Hidden file input -->
            <input
              ref="fileInput"
              type="file"
              style="display:none"
              accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt,.rtf,.csv,.zip,.png,.jpg,.jpeg,.gif,.svg,.mp4,.mp3,.py,.java,.cpp,.c,.js,.html,.css,.json,.xml,.r,.m,.ipynb"
              @change="onFileChange"
            >
          </div>
          <div v-if="questionForm.attachments && questionForm.attachments.length" class="mb-2">
            <b-table
              striped
              hover
              :no-border-collapse="true"
              :items="questionForm.attachments"
              :fields="attachmentsFields"
              responsive
            >
              <template v-slot:cell(actions)="data">
                <b-icon-trash :id="`delete-question-attachment-tooltip-${data.item.s3_key}`"
                              style="cursor: pointer;"
                              @click="initDeleteAttachment(data.item)"
                />
                <b-tooltip :target="`delete-question-attachment-tooltip-${data.item.s3_key}`"
                           delay="750"
                           triggers="hover"
                >
                  Delete {{ data.item.original_filename }}
                </b-tooltip>
                <span :id="`download-question-attachment-tooltip-${data.item.s3_key}`">
                  <a class="text-muted"
                     :href="`/api/questions/download-attachment/assignment/0/question/0/s3-key/${data.item.s3_key.split('/').pop()}`"
                  >
                    <b-icon-download
                      style="cursor: pointer;"
                    />
                  </a>
                </span>
                <b-tooltip :target="`download-question-attachment-tooltip-${data.item.s3_key}`"
                           delay="1000"
                           triggers="hover"
                >
                  Download {{ data.item.original_filename }}
                </b-tooltip>
              </template>
            </b-table>
          </div>
          <b-card border-variant="primary"
                  class="mb-3"
          >
            <div>
              <b-form-group
                key="source"
                label-for="non_technology_text"
              >
                <template v-if="questionForm.question_type === 'assessment'" v-slot:label>
                  <span style="cursor: pointer;" @click="toggleExpanded ('non_technology_text')">
                    HTML Block   <QuestionCircleTooltip id="open-ended-content-tooltip"/>
                    <b-tooltip target="open-ended-content-tooltip"
                               delay="250"
                               triggers="hover focus"
                    >
                      Questions may be created with or without an HTML block.  This block can be used by itself (typically for students who upload submissions) or can be used to enhance
                      questions that use one of the non-native auto-graded technologies.  For native questions, you can use the question's Prompt in lieu of the HTML block.
                    </b-tooltip>
                    <font-awesome-icon v-if="!editorGroups.find(group => group.id === 'non_technology_text').expanded"
                                       :icon="caretRightIcon" size="lg"
                    />
                    <font-awesome-icon v-if="editorGroups.find(group => group.id === 'non_technology_text').expanded"
                                       :icon="caretDownIcon" size="lg"
                    />
                  </span>
                </template>
              </b-form-group>
              <div
                v-show="editorGroups.find(group => group.id === 'non_technology_text').expanded || ('exposition' === questionForm.question_type)"
              >
                <b-container class="mt-2">
                  <b-row id="question-media-upload-html-block">
                    <QuestionMediaUpload
                      v-if="activeTabIndex === 1 && editorGroups.find(group => group.id === 'non_technology_text').expanded || ('exposition' === questionForm.question_type)"
                      :key="`question-media-upload-key-${questionMediaUploadKey}`"
                      :media-uploads="questionForm.media_uploads"
                      :question-media-upload-id="questionMediaUploadId"
                      :qti-json="questionForm.non_technology_text"
                      @updateQuestionMediaUploads="updateQuestionMediaUploads"
                      @deleteQuestionMediaUpload="deleteQuestionMediaUpload"
                      @updateQuestionTranscript="updateQuestionTranscript"
                      @updateQtiJson="updateQtiJson"
                    />
                  </b-row>
                </b-container>
                <ckeditor
                  id="non_technology_text"
                  ref="non_technology_text"
                  v-model="questionForm.non_technology_text"
                  tabindex="0"
                  required
                  :config="richEditorConfig"
                  :class="{ 'is-invalid': questionForm.errors.has('non_technology_text')}"
                  class="mb-2"
                  @namespaceloaded="onCKEditorNamespaceLoaded"
                  @ready="handleFixCKEditor()"
                  @focus="ckeditorKeyDown=true;questionForm.errors.clear('non_technology_text');"
                />
                <has-error :form="questionForm" field="non_technology_text"/>
              </div>
            </div>
          </b-card>

          <b-card border-variant="primary"
                  class="mb-3"
          >
            <div>
              <b-form-group
                label-for="open_ended_submission_type"
              >
                <template #label>
                  Open-Ended Submission Type
                  <QuestionCircleTooltip id="open-ended-submission-type-tooltip"/>
                  <b-tooltip target="open-ended-submission-type-tooltip"
                             delay="250"
                             triggers="hover focus"
                  >
                    Specify the open-ended submission type associated with this question. Instructors may override
                    this
                    at
                    the usage level.
                  </b-tooltip>
                </template>
                <b-form-select v-model="questionForm.open_ended_submission_type"
                               :options="openEndedSubmissionTypeOptions"
                               :style="['0',0,'no submission, manual grading'].includes(questionForm.open_ended_submission_type) ? 'width:250px' : 'width:100px'"
                               size="sm"
                />
              </b-form-group>
            </div>
          </b-card>
          <b-card border-variant="primary"
                  class="mb-3"
          >
            <div>
              <b-form-group
                v-if="questionForm.question_type === 'assessment'"
                label-cols-sm="4"
                label-cols-lg="3"
                label-for="technology"
              >
                <template #label>
                  Auto-Grade Tech Block
                  <QuestionCircleTooltip id="new-question-tooltip"/>
                  <b-tooltip target="new-question-tooltip"
                             delay="250"
                             triggers="hover focus"
                  >
                    Create a question using one of ADAPT's native question types (multplie choice, true/false,
                    numerical, etc.), use ADAPT's
                    editor to create a new WebWork question, or ADAPT can re-direct you to H5P/IMathAS so that you
                    can create new questions and
                    then import them back into ADAPT.
                  </b-tooltip>
                </template>
                <div class="d-flex flex-wrap align-items-center mt-1">
                  <b-form-radio-group
                    id="auto-grade-tech-block"
                    v-model="newAutoGradedTechnology"
                    name="question-type"
                    class="mr-2"
                    @input="openCreateAutoGradedTechnologyCode($event)"
                  >
                    <b-form-radio :value="null">
                      None
                    </b-form-radio>
                    <b-form-radio value="qti">
                      Native
                    </b-form-radio>
                    <b-form-radio value="h5p">
                      H5P
                    </b-form-radio>
                    <b-form-radio value="forge">
                      Forge
                    </b-form-radio>
                    <b-form-radio value="imathas">
                      IMathAS
                    </b-form-radio>

                    <b-form-radio value="webwork">
                      WeBWork
                    </b-form-radio>
                  </b-form-radio-group>
                  <b-form-select
                    v-if="newAutoGradedTechnology === 'webwork'"
                    v-model="webworkTemplate"
                    style="width:200px"
                    title="webwork templates"
                    size="sm"
                    :options="webworkTemplateOptions"
                    @change="setWebworkTemplate($event)"
                  />
                </div>
              </b-form-group>
              <div v-if="questionForm.technology === 'qti'">
                <b-form-group v-if="qtiQuestionType !== 'forge'"
                              label="Native Question Type"
                              label-cols-sm="3"
                              label-cols-lg="2"
                              label-for="native-question-type"
                >
                  <b-form-radio-group
                    id="native-question-type"
                    v-model="nativeType"
                    inline
                    name="native-question-type"
                    class="pt-2"
                    @change="initNativeType($event)"
                  >
                    <b-form-radio value="basic">
                      Basic
                      <QuestionCircleTooltip id="basic-questions-tooltip"/>
                      <b-tooltip target="basic-questions-tooltip"
                                 delay="250"
                                 triggers="hover focus"
                      >
                        <div style="max-width: 320px; font-size: 13px; line-height: 1.6;">
                          <p style="margin: 0 0 10px;">
                            Questions can be added in two ways:
                          </p>

                          <p style="margin: 0 0 4px;">
                            <strong style="font-weight: 500;">Bulk Import</strong>
                          </p>
                          <p style="margin: 0 0 10px;">
                            Export questions in QTI format from your LMS and import them via
                            the <strong style="font-weight: 500;">Bulk Import</strong> tab. Both QTI questions and
                            flashcards (which are not in QTI format) are supported. You can edit them in ADAPT after
                            importing.
                          </p>

                          <p style="margin: 0 0 4px;">
                            <strong style="font-weight: 500;">Manual Creation</strong>
                          </p>
                          <p style="margin: 0;">
                            Use the question editor below to build new questions directly.
                          </p>
                        </div>
                      </b-tooltip>
                    </b-form-radio>
                    <b-form-radio value="nursing">
                      Nursing
                      <QuestionCircleTooltip id="nursing-questions-tooltip"/>
                      <b-tooltip target="nursing-questions-tooltip"
                                 delay="250"
                                 triggers="hover focus"
                      >
                        Nursing questions are question types specifically written to prepare nursing students for the
                        NCLEX
                        exam.
                      </b-tooltip>
                    </b-form-radio>
                    <b-form-radio value="sketcher">
                      Sketcher
                    </b-form-radio>
                    <b-form-radio value="3d_model">
                      3D Model
                    </b-form-radio>
                    <b-modal id="modal-discuss-it"
                             title="Explanation of Discuss-it Questions"
                             size="xl"
                             no-close-on-backdrop
                    >
                      <div style="position: relative; padding-top: 65.26898734177216%;">
                        <iframe
                          src="https://customer-9mlff0qha6p39qdq.cloudflarestream.com/1db93b924be23b3ca4809cd889830fc6/iframe?poster=https%3A%2F%2Fcustomer-9mlff0qha6p39qdq.cloudflarestream.com%2F1db93b924be23b3ca4809cd889830fc6%2Fthumbnails%2Fthumbnail.jpg%3Ftime%3D%26height%3D600"
                          loading="lazy"
                          style="border: none; position: absolute; top: 0; left: 0; height: 100%; width: 100%;"
                          allow="accelerometer; gyroscope; autoplay; encrypted-media; picture-in-picture;"
                          allowfullscreen="true"
                        />
                      </div>
                      <template #modal-footer>
                        <b-button
                          variant="primary"
                          size="sm"
                          class="float-right"
                          @click="$bvModal.hide('modal-discuss-it')"
                        >
                          OK
                        </b-button>
                      </template>
                    </b-modal>
                    <b-form-radio value="discuss_it">
                      Discuss-it
                      <QuestionCircleTooltipModal :aria-label="'Explanation of Discuss-it'"
                                                  :modal-id="'modal-discuss-it'"
                                                  :color-class="'font-bold'"
                      />
                    </b-form-radio>
                    <b-form-radio value="accounting">
                      Accounting
                    </b-form-radio>
                    <b-form-radio value="all">
                      All
                    </b-form-radio>
                  </b-form-radio-group>
                </b-form-group>
                <b-form-group>
                  <div v-if="nativeType === 'sketcher'">
                    <div class="mb-2">
                      If you need help getting started, please visit
                      <ConsultInsight
                        id="consult-insight-webwork"
                        :url="'https://commons.libretexts.org/insight/adapt-sketcher'"
                        :formatting-class="''"
                      />
                      .
                    </div>
                    <b-form-radio v-model="qtiQuestionType" name="qti-question-type" value="submit_molecule"
                                  @change="initQTIQuestionType($event)"
                    >
                      Submit Molecule
                    </b-form-radio>
                    <b-form-radio v-model="qtiQuestionType" name="qti-question-type" value="marker"
                                  @change="initQTIQuestionType($event)"
                    >
                      Marker
                    </b-form-radio>
                  </div>
                  <div v-if="nativeType === '3d_model'" v-show="false">
                    <b-form-radio v-model="qtiQuestionType" name="qti-question-type"
                                  value="three_d_model_multiple_choice"
                                  @change="initQTIQuestionType($event)"
                    >
                      Multiple Choice
                    </b-form-radio>
                    <b-form-radio v-model="qtiQuestionType" name="qti-question-type"
                                  value="three_d_model_multiple_answer"
                                  @change="initQTIQuestionType($event)"
                    >
                      Multiple Answer
                    </b-form-radio>
                  </div>
                  <div v-if="nativeType === 'accounting'">
                    <div class="mb-2">
                      If you need help getting started, please visit
                      <ConsultInsight
                        id="consult-insight-webwork"
                        :url="'https://commons.libretexts.org/insight/adapt-accounting'"
                        :formatting-class="''"
                      />
                      .
                    </div>
                    <b-form-radio v-model="qtiQuestionType" name="qti-question-type"
                                  value="accounting_journal_entry"
                                  @change="initQTIQuestionType($event)"
                    >
                      Journal Entry
                    </b-form-radio>
                    <b-form-radio v-model="qtiQuestionType" name="qti-question-type"
                                  value="accounting_report"
                                  @change="initQTIQuestionType($event)"
                    >
                      Report
                    </b-form-radio>
                    <b-form-radio v-model="qtiQuestionType"
                                  name="qti-question-type"
                                  value="accounting_multi_part_computation"
                                  @change="initQTIQuestionType($event)"
                    >
                      Multi-part Computation
                    </b-form-radio>
                  </div>
                  <div v-if="['all','basic'].includes(nativeType)">
                    <b-form-radio v-model="qtiQuestionType" name="qti-question-type" value="multiple_choice"
                                  @change="initQTIQuestionType($event)"
                    >
                      Multiple Choice
                    </b-form-radio>
                    <b-form-radio v-model="qtiQuestionType" name="qti-question-type" value="true_false"
                                  @change="initQTIQuestionType($event)"
                    >
                      True/False
                    </b-form-radio>
                    <b-form-radio v-model="qtiQuestionType" name="qti-question-type" value="numerical"
                                  @change="initQTIQuestionType($event)"
                    >
                      Single Numerical
                    </b-form-radio>
                    <b-form-radio v-model="qtiQuestionType" name="qti-question-type" value="multi_numerical"
                                  @change="initQTIQuestionType($event)"
                    >
                      Multi Numerical
                    </b-form-radio>
                    <b-form-radio v-model="qtiQuestionType" name="qti-question-type" value="multiple_answers"
                                  @change="initQTIQuestionType($event)"
                    >
                      Multiple Answer
                    </b-form-radio>
                    <b-form-radio v-model="qtiQuestionType" name="qti-question-type" value="fill_in_the_blank"
                                  @change="initQTIQuestionType($event)"
                    >
                      Fill-in-the-blank
                    </b-form-radio>
                    <b-form-radio v-model="qtiQuestionType" name="qti-question-type" value="select_choice"
                                  @change="initQTIQuestionType($event)"
                    >
                      Select Choice
                    </b-form-radio>
                    <b-form-radio v-model="qtiQuestionType"
                                  name="qti-question-type"
                                  value="matching"
                                  @change="initQTIQuestionType($event)"
                    >
                      Matching
                    </b-form-radio>
                    <b-form-radio v-model="qtiQuestionType"
                                  name="qti-question-type"
                                  value="flashcard"
                                  @change="initQTIQuestionType($event)"
                    >
                      Flashcard
                    </b-form-radio>
                  </div>
                  <div v-if="['all','nursing'].includes(nativeType)">
                    <b-form-radio v-model="qtiQuestionType" name="qti-question-type" value="bow_tie"
                                  @change="initQTIQuestionType($event)"
                    >
                      Bow Tie
                    </b-form-radio>
                    <b-form-radio v-model="qtiQuestionType" name="qti-question-type" value="multiple_choice"
                                  @change="initQTIQuestionType($event)"
                    >
                      Multiple Choice
                    </b-form-radio>
                    <b-form-radio v-model="qtiQuestionType" name="qti-question-type" value="matrix_multiple_choice"
                                  @change="initQTIQuestionType($event)"
                    >
                      Matrix Multiple Choice
                    </b-form-radio>
                    <b-form-radio v-model="qtiQuestionType" name="qti-question-type"
                                  value="multiple_response_select_n"
                                  @change="initQTIQuestionType($event)"
                    >
                      Multiple Response Select N
                    </b-form-radio>
                    <b-form-radio v-model="qtiQuestionType" name="qti-question-type"
                                  value="multiple_response_select_all_that_apply"
                                  @change="initQTIQuestionType($event)"
                    >
                      Multiple Response Select All That Apply
                    </b-form-radio>
                    <b-form-radio v-model="qtiQuestionType" name="qti-question-type" value="highlight_table"
                                  @change="initQTIQuestionType($event)"
                    >
                      Highlight Table
                    </b-form-radio>
                    <b-form-radio v-model="qtiQuestionType" name="qti-question-type" value="highlight_text"
                                  @change="initQTIQuestionType($event)"
                    >
                      Highlight Text
                    </b-form-radio>
                    <b-form-radio v-model="qtiQuestionType" name="qti-question-type"
                                  value="multiple_response_grouping"
                                  @change="initQTIQuestionType($event)"
                    >
                      Multiple Response Grouping
                    </b-form-radio>
                    <b-form-radio v-model="qtiQuestionType" name="qti-question-type" value="drop_down_table"
                                  @change="initQTIQuestionType($event)"
                    >
                      Drop-Down Table
                    </b-form-radio>
                    <b-form-radio v-model="qtiQuestionType" name="qti-question-type" value="drop_down_rationale_dyad"
                                  @change="initQTIQuestionType($event)"
                    >
                      Drop-Down Dyad
                    </b-form-radio>
                    <b-form-radio v-model="qtiQuestionType" name="qti-question-type" value="drop_down_rationale_triad"
                                  @change="initQTIQuestionType($event)"
                    >
                      Drop-Down Triad
                    </b-form-radio>
                    <b-form-radio v-model="qtiQuestionType" name="qti-question-type" value="select_choice"
                                  @change="initQTIQuestionType($event)"
                    >
                      Drop-Down Cloze
                    </b-form-radio>
                    <b-form-radio v-model="qtiQuestionType" name="qti-question-type" value="matrix_multiple_response"
                                  @change="initQTIQuestionType($event)"
                    >
                      Matrix Multiple Response
                    </b-form-radio>
                    <b-form-radio v-model="qtiQuestionType" name="qti-question-type" value="drag_and_drop_cloze"
                                  @change="initQTIQuestionType($event)"
                    >
                      Drag and Drop Cloze
                    </b-form-radio>
                  </div>
                  <div v-if="['all'].includes(nativeType)">
                    <b-form-radio v-model="qtiQuestionType" name="qti-question-type" value="sketcher"
                                  @change="initQTIQuestionType('submit_molecule')"
                    >
                      Sketcher
                    </b-form-radio>
                  </div>
                </b-form-group>
                <div v-if="qtiQuestionType === 'highlight_table'">
                  <b-alert show variant="info">
                    Optionally add a prompt for this question. Then, in each row, add a description in the first
                    column.
                    Then in
                    the second column, write text, where text within
                    brackets will automatically become your highlighted text. Once the text is added, determine
                    whether
                    it
                    is a
                    correct answer or a distractor.
                  </b-alert>
                </div>
                <div v-if="qtiQuestionType === 'drop_down_table'">
                  <b-alert show variant="info">
                    Write out a prompt, then add a series of drop-downs to your table. Each drop-down should have at
                    least
                    two
                    selections.
                  </b-alert>
                </div>
                <div v-if="qtiQuestionType === 'highlight_text'">
                  <b-alert show variant="info">
                    Write out a prompt, where text within square brackets will automatically become your highlighted
                    text. Once
                    the
                    text is added, determine whether it is a correct answer or a distractor.
                  </b-alert>
                </div>
                <div v-if="qtiQuestionType === 'select_choice'">
                  <b-alert show variant="info">
                    Using brackets, place the correct answer in the location that you want the select choice to
                    appear.
                    Then, add the choices below. <br>
                    <br>
                    Example. [Star Wars] took place in a [galaxy] far, far away.
                    <br><br>
                    Note that bracketed words should only appear once. If you need to use the same correct answer
                    multiple
                    times,
                    you can use a dummy identifier such as [Star Wars-1] where the "1" should be increased each time
                    you
                    need to use the same correct answer.
                    Then, below, you can update the correct answer manually.<br>
                    <br>Each student will receive a randomized ordering of the choices.<br>
                  </b-alert>
                </div>
                <div v-if="qtiQuestionType === 'drop_down_rationale_dyad'">
                  <b-alert show variant="info">
                    The structure of a “dyad rationale” question is, “The client is at risk for developing X as
                    evidenced by
                    Y.”
                    X and Y are pulled from different pools of choices.
                    Using brackets, place a non-space-containing identifier to show where you want “X” and “Y” placed.
                    Example: The client is at risk for [disease] as evidenced by [type-of-assessment]. Then, under the
                    choices
                    column that appears, add the correct choice for that selection, then add the distractors for each
                    indicator.
                    Each student will receive a randomized ordering of the choices.
                  </b-alert>
                </div>
                <div v-if="qtiQuestionType === 'drop_down_rationale_triad'">
                  <b-alert show variant="info">
                    <p>
                      The structure of a “triad rationale” question is, “The client is at risk for developing X as
                      evidenced
                      by
                      Y
                      and Z.”
                      X is pulled from a pool of choices (the condition) and Y and Z are pulled from the same pool of
                      choices
                      (the
                      rationales).
                      Using [condition] and [rationale], indicate where you want the condition and the two rationales
                      placed.
                    </p>
                    <p>Example: The client is at risk for [condition] as evidenced by [rationale] and [rationale].</p>
                    <p>
                      Next, under the Condition column, add the correct choice and distractors. Under the Rationale
                      column,
                      add
                      the two correct rationales and distractors.
                    </p>
                    Each student will receive a randomized ordering of the choices.
                  </b-alert>
                </div>
                <div v-if="qtiQuestionType === 'matching'">
                  <b-alert show variant="info">
                    Create a list of terms to match along with their matching terms. Matching terms can include media
                    such
                    as images.
                    Optionally, add distractors which do not satisfy any of the matches.
                  </b-alert>
                </div>
                <div v-if="qtiQuestionType === 'true_false'">
                  <b-alert show variant="info">
                    Write a question prompt and then select either True or False for the correct answer.
                  </b-alert>
                </div>
                <div v-if="newAutoGradedTechnology === 'forge'">
                  <b-alert show variant="info">
                    Write a question prompt and then add the Forge question to your assignment. At that point you can
                    add drafts/final submission and determine due dates.
                  </b-alert>
                </div>
                <div v-if="qtiQuestionType === 'multiple_choice'">
                  <b-alert show variant="info">
                    Write a question prompt and then create a selection of answers, choosing the correct answer from
                    the
                    list. Students will receive a shuffled ordering of the selection.
                    Optionally provide feedback at the individual question level or general feedback for a correct
                    response, an incorrect response, or any response.
                  </b-alert>
                </div>
                <div v-if="qtiQuestionType === 'numerical'">
                  <b-alert show variant="info">
                    Write a question prompt which requires a numerical response, specifying the margin of error
                    accepted in the response.
                    Optionally provide general feedback for a correct response, an incorrect response, or any
                    response.
                  </b-alert>
                </div>
                <div v-if="qtiQuestionType === 'multi_numerical'">
                  <b-alert show variant="info">
                    Type your question in the editor, then click the U toolbar button to underline each numerical
                    answer.
                    For example: 60% of 30 is <u>18</u> while 10% of 30 is <u>3</u>. A table will appear below the
                    editor where you can set the correct tolerance for each blank.
                    Tolerance can be set in Absolute (±) terms for a fixed range, or Relative (%) terms for a
                    percentage-based range
                  </b-alert>
                </div>
                <div v-if="qtiQuestionType === 'drag_and_drop_cloze'">
                  <b-alert show variant="info">
                    Bracket off the portions of the text where you would like the Drag and Drop Cloze to occur, using
                    a
                    bracketed response
                    to
                    denote the correct answer. Then, add distractors below. Example: The client is at risk for
                    developing
                    [high blood pressure]
                    and [a heart attack]. Students will then see a drop-down for each item and will only be able to
                    choose
                    each
                    item once.
                    This question mimics the Drag and Drop Cloze functionality in a way that is accessible. Because of
                    this,
                    there will be a single pool of choices.
                  </b-alert>
                </div>
                <div v-if="qtiQuestionType === 'bow_tie'">
                  <b-alert show variant="info">
                    Write a question prompt and then add two correct Actions to Take, one correct Potential Condition,
                    and
                    two
                    Parameters to Monitor. Each of these groups should have at least one Distractor.
                  </b-alert>
                </div>
                <div v-if="qtiQuestionType === 'multiple_response_select_n'">
                  <b-alert show variant="info">
                    Using brackets and associated text, indicate the number of correct responses.
                    Example. The [3] most likely reasons the patient has high blood pressure are:
                  </b-alert>
                </div>
                <div v-if="qtiQuestionType === 'multiple_response_select_all_that_apply'">
                  <b-alert show variant="info">
                    Write a question prompt where students have to select all responses that apply. Example. Select
                    the
                    following activities which contribute to heart disease:
                  </b-alert>
                </div>
                <div v-if="qtiQuestionType === 'multiple_response_grouping'">
                  <b-alert show variant="info">
                    Write a question prompt and then create groupings with sets of checkboxes, checking off the
                    correct
                    responses for each grouping.
                  </b-alert>
                </div>
                <div v-if="qtiQuestionType === 'matrix_multiple_choice'">
                  <b-alert show variant="info">
                    Write a question prompt and then construct a table with one correct choice per row, selecting that
                    choice
                    by
                    clicking
                    on the corresponding radio button.
                  </b-alert>
                </div>
                <div v-if="qtiQuestionType === 'fill_in_the_blank' && qtiJson">
                  <b-alert show variant="info">
                    Create a question with fill in the blanks by underlining
                    the correct responses. Example. A <u>stitch</u> in time saves <u>nine</u>. If you would like to
                    accept multiple answers, separate them with a vertical bar: "|". Example.
                    <u>January|February</u> is a month that comes before March.
                  </b-alert>
                  <b-container v-if="questionForm.technology === 'qti'" class="mt-2">
                    <b-row>
                      <QuestionMediaUpload v-if="activeTabIndex === 1"
                                           :key="`question-media-upload-key-${questionMediaUploadKey}`"
                                           :media-uploads="questionForm.media_uploads"
                                           :question-media-upload-id="questionMediaUploadId"
                                           :qti-json="JSON.stringify(qtiJson)"
                                           @updateQuestionMediaUploads="updateQuestionMediaUploads"
                                           @deleteQuestionMediaUpload="deleteQuestionMediaUpload"
                                           @updateQuestionTranscript="updateQuestionTranscript"
                                           @updateQtiJson="updateQtiJson"
                      />
                    </b-row>
                  </b-container>
                </div>
                <div
                  v-if="['accounting_report',
                         'accounting_multi_part_computation',
                         'forge',
                         'matching',
                         'multiple_answers',
                         'true_false',
                         'multiple_choice',
                           'numerical',
                         'multiple_response_select_all_that_apply',
                         'multiple_response_select_n',
                         'matrix_multiple_response',
                         'multiple_response_grouping',
                         'drop_down_table',
                         'drag_and_drop_cloze',
                         'matrix_multiple_choice',
                         'bow_tie',
                         'highlight_text',
                         'highlight_table',
                         'three_d_model_multiple_choice',
                         'submit_molecule',
                         'marker',
                         'discuss_it'].includes(qtiQuestionType) && qtiJson"
                  class="mb-2"
                >
                  <b-container
                    v-if="questionForm.technology === 'qti' && !['submit_molecule','marker'].includes(qtiQuestionType)"
                    class="mt-2"
                  >
                    <b-row>
                      <QuestionMediaUpload v-if="qtiQuestionType !== 'discuss_it' && activeTabIndex === 1"
                                           :key="`question-media-upload-key-${questionMediaUploadKey}`"
                                           :media-uploads="questionForm.media_uploads"
                                           :question-media-upload-id="questionMediaUploadId"
                                           :qti-json="JSON.stringify(qtiJson)"
                                           @updateQuestionMediaUploads="updateQuestionMediaUploads"
                                           @deleteQuestionMediaUpload="deleteQuestionMediaUpload"
                                           @updateQuestionTranscript="updateQuestionTranscript"
                                           @updateQtiJson="updateQtiJson"
                      />
                    </b-row>
                  </b-container>
                  <b-card header="default" :header-html="getPromptHeader()">
                    <ckeditor
                      id="qtiItemPrompt"
                      :key="`question-type-${qtiQuestionType}`"
                      v-model="qtiJson['prompt']"
                      tabindex="0"
                      required
                      :config="shorterRichEditorConfig"
                      class="pb-3"
                      @namespaceloaded="onCKEditorNamespaceLoaded"
                      @ready="handleFixCKEditor()"
                      @focus="ckeditorKeyDown=true;questionForm.errors.clear('qti_prompt')"
                    />
                    <ErrorMessage v-if="questionForm.errors.get(`qti_prompt`)"
                                  :message="questionForm.errors.get(`qti_prompt`)"
                    />
                  </b-card>
                  <div v-if="qtiQuestionType === 'discuss_it'">
                    <div v-if="isEdit" class="pt-3">
                      <b-alert variant="info" show>
                        If there are student comments and you edit any of the associated media
                        the comments will be deleted.
                      </b-alert>
                    </div>
                    <QuestionMediaUpload v-if="activeTabIndex === 1"
                                         :key="`question-media-upload-key-${questionMediaUploadKey}`"
                                         :media-uploads="questionForm.media_uploads"
                                         :qti-json="JSON.stringify(qtiJson)"
                                         :question-media-upload-id="questionMediaUploadId"
                                         :is-discuss-it="true"
                                         @updateQuestionMediaUploadsOrder="updateQuestionMediaUploadsOrder"
                                         @updateQuestionMediaUploads="updateQuestionMediaUploads"
                                         @deleteQuestionMediaUpload="deleteQuestionMediaUpload"
                                         @updateQuestionTranscript="updateQuestionTranscript"
                                         @editDiscussItText="editDiscussItText"
                                         @initDiscussItText="initDiscussItText"
                    />
                  </div>
                </div>
                <div v-if="'accounting_report' === qtiQuestionType">
                  <AccountingReport
                    :qti-json="qtiJson"
                    :question-form="questionForm"
                    @updateQtiJson="updateQtiJson"
                  />
                </div>
                <div v-if="'accounting_journal_entry' === qtiQuestionType">
                  <AccountingJournalEntry
                    :qti-json="qtiJson"
                    :question-form="questionForm"
                    @updateQtiJson="updateQtiJson"
                  />
                </div>
                <div v-if="'accounting_multi_part_computation' === qtiQuestionType">
                  <AccountingMultiPartComputation
                    :qti-json="qtiJson"
                    :question-form="questionForm"
                    @updateQtiJson="updateQtiJson"
                  />
                </div>
                <div v-if="['three_d_model_multiple_choice'].includes(qtiQuestionType)" class="mb-2">
                  <ThreeDModel
                    :qti-json="qtiJson"
                    :three-d-model-parameter-errors="threeDModelParameterErrors"
                    :three-d-model-solution-structure-errors="threeDModelSolutionStructureErrors"
                    :three-d-model-rich-editor-config="threeDModelRichEditorConfig"
                    @updateQtiJson="updateQtiJson"
                  />
                </div>
                <div v-if="(isLocalMe || user.id === 36892)">
                  Debugging: {{ qtiJson }}
                  {{ qtiJson.matchStereo }}
                </div>
                <div v-if="['submit_molecule','marker'].includes(qtiQuestionType)">
                  <div v-if="qtiQuestionType === 'submit_molecule'" class="border border-dark p-2"
                       style="width:320px;margin:auto"
                  >
                    <b-form-checkbox
                      id="match_stereo"
                      v-model="qtiJson.matchStereo"
                      name="match_stereo"
                      value="1"
                      unchecked-value="0"
                    >
                      Only approve identical stereoisomers
                    </b-form-checkbox>
                  </div>
                  <div v-show="qtiQuestionType !== 'marker' || !qtiJson.solutionStructure">
                    <b-form-group
                      class="pt-2"
                      label-cols-sm="1"
                      label-cols-lg="1"
                      label="SMILES"
                      label-for="smiles"
                    >
                      <template v-slot:label>
                        SMILES

                        <QuestionCircleTooltip id="smiles-tooltip"/>
                        <b-tooltip target="smiles-tooltip"
                                   delay="250"
                                   triggers="hover focus"
                        >
                          You can manually enter the smiles associated with the molecule.
                        </b-tooltip>
                      </template>
                      <b-form-row>
                        <b-form-input
                          id="smiles"
                          v-model="smiles"
                          type="text"
                          size="sm"
                          style="width:80%"
                          class="mr-2"
                        />
                        <b-button size="sm" @click="convertToMolecule">
                          Convert to Molecule
                        </b-button>
                      </b-form-row>
                    </b-form-group>
                    <StructureImageUploader/>
                  </div>
                  <div id="to-sketcher-component" @click="handleSketcherClick"/>
                  <div v-show="qtiQuestionType ==='marker'" class="mb-2">
                    <div v-if="!qtiJson.solutionStructure">
                      <b-button
                        variant="primary"
                        size="sm"
                        @click="setMolecule"
                      >
                        Set Molecule
                      </b-button>
                    </div>
                    <div v-else>
                      <b-button
                        variant="info"
                        size="sm"
                        @click="updateMarks"
                      >
                        Update Marks
                      </b-button>
                      <b-button
                        variant="danger"
                        size="sm"
                        @click="initUpdateStructure"
                      >
                        Update Structure
                      </b-button>
                    </div>
                  </div>
                  <div v-if="qtiQuestionType === 'marker'" class="d-inline-flex">
                    <label class="mr-2">
                      Scoring
                      <QuestionCircleTooltip id="marker-scoring-tooltip"/>
                    </label>
                    <b-tooltip target="marker-scoring-tooltip" delay="250" triggers="hover focus">
                      With exclusive scoring, the student will receive no partial credit; they will receive partial
                      credit with inclusive score.
                    </b-tooltip>
                    <b-form-radio-group
                      v-model="qtiJson.partialCredit"
                    >
                      <b-form-radio value="exclusive">
                        Exclusive
                      </b-form-radio>

                      <b-form-radio value="inclusive">
                        Inclusive
                      </b-form-radio>
                    </b-form-radio-group>
                    <b-form-checkbox
                      v-show="qtiJson.partialCredit === 'inclusive'"
                      v-model="qtiJson.oneHundredPercentOverride"
                      value="1"
                      unchecked-value="0"
                      class="custom-checkbox"
                    >
                      100% override
                      <QuestionCircleTooltip id="100-percent-override-tooltip"/>
                      <b-tooltip target="100-percent-override-tooltip"
                                 delay="250"
                                 triggers="hover focus"
                      >
                        If checked, students will not be able to guess by simply marking all atoms/bonds.
                      </b-tooltip>
                    </b-form-checkbox>
                    <div/>
                  </div>
                  <MultipleAnswersAdvanced
                    v-if="qtiQuestionType === 'marker' && qtiJson.solutionStructure.atoms && qtiJson.solutionStructure.bonds"
                    ref="multipleAnswersAdvanced"
                    :key="`multiple-answers-advanced-${multipleAnswersAdvancedKey}`"
                    :qti-json="qtiJson"
                    :question-form="questionForm"
                    @setAtomsAndBonds="setAtomsAndBonds"
                  />
                </div>
                <DragAndDropCloze v-if="qtiQuestionType === 'drag_and_drop_cloze'"
                                  ref="dragAndDropCloze"
                                  :qti-json="qtiJson"
                                  :question-form="questionForm"
                />

                <MatrixMultipleChoice v-if="qtiQuestionType === 'matrix_multiple_choice'"
                                      ref="dropDownTable"
                                      :qti-json="qtiJson"
                                      :question-form="questionForm"
                />
                <DropDownTable v-if="qtiQuestionType === 'drop_down_table'"
                               ref="dropDownTable"
                               :qti-json="qtiJson"
                               :question-form="questionForm"
                />

                <MatrixMultipleResponse v-if="qtiQuestionType === 'matrix_multiple_response'"
                                        ref="matrixMultipleResponse"
                                        :qti-json="qtiJson"
                                        :question-form="questionForm"
                />
                <MultipleResponseGrouping v-if="qtiQuestionType === 'multiple_response_grouping'"
                                          ref="multipleResponseGrouping"
                                          :qti-json="qtiJson"
                                          :question-form="questionForm"
                />

                <MultipleResponseSelectAllThatApplyOrSelectN
                  v-if="['multiple_response_select_all_that_apply','multiple_response_select_n'].includes(qtiQuestionType)"
                  ref="MultipleResponseSelectAllThatApplyOrSelectN"
                  :qti-json="qtiJson"
                  :question-form="questionForm"
                />
                <BowTie v-if="qtiQuestionType === 'bow_tie'"
                        ref="bowTie"
                        :qti-json="qtiJson"
                        :question-form="questionForm"
                        class="p-0"
                />
                <FillInTheBlank v-if="qtiQuestionType === 'fill_in_the_blank'"
                                ref="fillInTheBlank"
                                :qti-json="qtiJson"
                                :rich-editor-config="richEditorConfig"
                                :question-form="questionForm"
                                class="p-0"
                                @setCKEditorKeydownAsTrue="setCKEditorKeydownAsTrue"
                />
                <HighlightTable v-if="qtiQuestionType === 'highlight_table'"
                                ref="HighlightTable"
                                :qti-json="qtiJson"
                                :question-form="questionForm"
                />
                <SingleNumerical v-if="qtiQuestionType === 'numerical'"
                                 ref="SingleNumerical"
                                 :qti-json="qtiJson"
                                 :question-form="questionForm"
                />
                <MultiNumerical v-if="qtiQuestionType === 'multi_numerical'"
                                ref="MultiNumerical"
                                :key="`multi-numerical-${isEdit}`"
                                :qti-json="qtiJson"
                                :question-form="questionForm"
                                :rich-editor-config="richEditorConfig"
                                @setCKEditorKeydownAsTrue="setCKEditorKeydownAsTrue"
                />

                <div
                  v-if="['drop_down_rationale_dyad','drop_down_rationale_triad','select_choice'].includes(qtiQuestionType)"
                >
                  <div v-if="qtiQuestionType === 'select_choice'">
                    <QuestionMediaUpload v-if="activeTabIndex === 1"
                                         :key="`question-media-upload-key-${questionMediaUploadKey}`"
                                         :media-uploads="questionForm.media_uploads"
                                         :qti-json="JSON.stringify(qtiJson)"
                                         :question-media-upload-id="questionMediaUploadId"
                                         @updateQuestionMediaUploads="updateQuestionMediaUploads"
                                         @deleteQuestionMediaUpload="deleteQuestionMediaUpload"
                                         @updateQuestionTranscript="updateQuestionTranscript"
                                         @updateQtiJson="updateQtiJson"
                    />
                  </div>
                  <ckeditor
                    id="qtiItemBody"
                    :key="`question-type-${qtiQuestionType}`"
                    v-model="qtiJson.itemBody"
                    tabindex="0"
                    required
                    :config="richEditorConfig"
                    :class="{ 'is-invalid': questionForm.errors.has('qti_item_body')}"
                    class="pb-3"
                    @namespaceloaded="onCKEditorNamespaceLoaded"
                    @ready="handleFixCKEditor()"
                    @focus="ckeditorKeyDown=true;questionForm.errors.clear('qti_item_body')"
                    @keydown="questionForm.errors.clear('qti_item_body')"
                  />
                  <has-error :form="questionForm" field="qti_item_body"/>
                </div>
                <SelectChoiceDropDownRationale
                  v-if="['select_choice','drop_down_rationale_dyad'].includes(qtiQuestionType)"
                  ref="selectChoiceDropDownRationale"
                  :qti-json="qtiJson"
                  :question-form="questionForm"
                />
                <DropDownRationaleTriad
                  v-if="qtiQuestionType === 'drop_down_rationale_triad'"
                  ref="dropDownTriad"
                  :qti-json="qtiJson"
                  :question-form="questionForm"
                />

                <Matching v-if="qtiQuestionType === 'matching'"
                          ref="matching"
                          :qti-json="qtiJson"
                          :question-form="questionForm"
                          :matching-rich-editor-config="matchingRichEditorConfig"
                />
                <Flashcard
                  v-if="qtiQuestionType === 'flashcard'"
                  ref="flashcard"
                  :initial-card="qtiJson.card || null"
                  :rich-editor-config="richEditorConfig"
                  @file-selected="handleFlashcardFileUpload"
                />

                <MultipleAnswers v-if="qtiQuestionType === 'multiple_answers'"
                                 ref="multipleAnswers"
                                 :qti-json="qtiJson"
                                 :question-form="questionForm"
                                 :matching-rich-editor-config="matchingRichEditorConfig"
                                 :multiple-response-rich-editor-config="multipleResponseRichEditorConfig"
                                 class="p-0"
                />

                <MultipleChoiceTrueFalse v-if="['true_false','multiple_choice'].includes(qtiQuestionType)"
                                         ref="multipleChoiceTrueFalse"
                                         :key="qtiQuestionType"
                                         :qti-json="qtiJson"
                                         :question-form="questionForm"
                                         :simple-choice-config="simpleChoiceConfig"
                />
                <HighlightText v-if="qtiQuestionType === 'highlight_text'"
                               ref="HighlightText"
                               :qti-json="qtiJson"
                               :question-form="questionForm"
                />
                <div class="pb-2">
                  <b-card
                    v-if="['highlight_text','multiple_response_select_all_that_apply'].includes(qtiQuestionType)"
                    header="default"
                  >
                    <template #header>
                      <span class="ml-2 h7">Checkmark Feedback   <QuestionCircleTooltip id="checkmarks-tooltip"/>
                        <b-tooltip target="checkmarks-tooltip"
                                   delay="250"
                                   triggers="hover focus"
                        >
                          These options do not affect how a question is scored but do affect how feedback is presented to the
                          students.
                        </b-tooltip></span>
                    </template>
                    <b-form-group>
                      <b-form-radio-group
                        id="checkmarks"
                        v-model="qtiJson.check_marks"
                        inline
                        name="checkmarks"
                        class="pt-2"
                      >
                        <b-form-radio value="correctly checked answers and correctly unchecked incorrect answers">
                          For correct answers that were checked and incorrect answers that were not checked
                        </b-form-radio>
                        <b-form-radio value="only for correct answers that were checked">
                          Only for correct answers that were checked
                        </b-form-radio>
                      </b-form-radio-group>
                    </b-form-group>
                  </b-card>
                </div>
                <div class="pb-2">
                  <b-card
                    v-if="['multiple_choice', 'numerical'].includes(qtiQuestionType)
                      || nursingQuestions.includes(qtiQuestionType)
                      || qtiQuestionType.includes('drop_down_rationale')
                      || (qtiQuestionType === 'select_choice' && nativeType === 'nursing')"
                    header="default"
                  >
                    <template #header>
                      <span class="ml-2 h7">General Feedback</span>
                    </template>
                    <div v-for="(generalFeedback,index) in generalFeedbacks"
                         :key="`feedback-${generalFeedback.label}`"
                    >
                      <div
                        v-if="generalFeedback.label !== 'Any Response'
                          || !((nursingQuestions.includes(qtiQuestionType)||(qtiQuestionType === 'select_choice' && nativeType === 'nursing'))
                            || qtiQuestionType.includes('drop_down_rationale'))"
                      >
                        <b-form-group
                          :label-for="generalFeedback.id"
                          class="mb-0"
                        >
                          <template v-slot:label>
                            <span class="font-weight-bold">{{ generalFeedback.label }}</span>
                            <b-icon icon="pencil"
                                    :variant="generalFeedback.editorShown ? 'secondary' : 'primary'"
                                    :aria-label="`Edit ${generalFeedback.label} feedback`"
                                    @click="toggleGeneralFeedbackEditorShown(generalFeedback.key,true)"
                            />
                          </template>
                          <div v-if="generalFeedback.editorShown">
                            <ckeditor
                              :id="generalFeedback.id"
                              v-model="qtiJson.feedback[generalFeedback.key]"
                              tabindex="0"
                              :config="simpleChoiceFeedbackConfig"
                              @namespaceloaded="onCKEditorNamespaceLoaded"
                              @ready="handleFixCKEditor()"
                            />
                            <div class="mt-2">
                              <b-button
                                size="sm"
                                variant="primary"
                                @click="toggleGeneralFeedbackEditorShown(generalFeedback.key,false)"
                              >
                                Close
                              </b-button>
                            </div>
                          </div>
                          <div v-if="qtiJson.feedback && !generalFeedback.editorShown">
                            <span v-html="qtiJson.feedback[generalFeedback.key]"/>
                          </div>
                        </b-form-group>
                        <hr
                          v-if="(index !==2 && !nursingQuestions.includes(qtiQuestionType)) || (index === 0 && nursingQuestions.includes(qtiQuestionType))"
                        >
                      </div>
                    </div>
                  </b-card>
                </div>
              </div>
              <b-form-group v-if="showPreexistingWebworkFilePath"
                            label-cols-sm="4"
                            label-cols-lg="3"
                            label="ADAPT ID or WeBWork File Path"
                            label-for="pre_existing_webwork_problem"
              >
                <b-form-row>
                  <b-form-input
                    id="pre_existing_webwork_problem"
                    v-model="preExistingWebworkFilePath"
                    type="text"
                    size="sm"
                    style="width:500px"
                    class="mr-2"
                  />
                  <b-button size="sm"
                            @click="updateTemplateWithPreexistingWebworkFilePath(preExistingWebworkFilePath)"
                  >
                    <span v-if="!updatingTempalteWithPreexistingWebworkFilePath">Update template</span>
                    <span v-if="updatingTempalteWithPreexistingWebworkFilePath"><b-spinner small type="grow"/>
                      Updating...
                    </span>
                  </b-button>
                </b-form-row>
              </b-form-group>
              <div v-if="['imathas','h5p'].includes(questionForm.technology)">
                <p>
                  If you haven't already created the {{ getTextFromTechnology(questionForm.technology) }} question,
                  you
                  will need to visit
                  {{ getTextFromTechnology(questionForm.technology) }}'s <a
                  :href="questionForm.technology === 'h5p' ? h5pUrl : imathASUrl" target="_blank"
                >question editor</a>. Please note that you must have
                  access to the editor.
                </p>
              </div>
              <b-form-group
                v-if="['imathas','h5p'].includes(questionForm.technology)"
                :label-cols-sm="questionForm.technology === 'h5p' ? 2 : 3"
                :label-cols-lg="questionForm.technology=== 'h5p' ? 1 : 2"
                label-for="technology_id"
                :label="`${getTextFromTechnology(questionForm.technology)} ID`"
              >
                <b-form-row>
                  <div>
                    <b-form-input
                      id="technology_id"
                      v-model="questionForm.technology_id"
                      type="text"
                      :class="{ 'is-invalid': questionForm.errors.has('technology_id'), 'numerical-input' : questionForm.technology !== 'webwork' }"
                      @keydown="questionForm.errors.clear('technology_id')"
                    />
                    <has-error :form="questionForm" field="technology_id"/>
                  </div>
                  <div class="mt-1 ml-3">
                    <b-button v-if="questionForm.technology === 'h5p' && questionForm.technology_id" size="sm"
                              variant="outline-primary"
                              @click="viewInLibreStudio(questionForm.technology_id)"
                    >
                      View in LibreStudio
                    </b-button>
                  </div>
                </b-form-row>
              </b-form-group>
              <div v-show="webworkEditorShown">
                <div class="mb-2">
                  If you need help getting started, please visit
                  <ConsultInsight
                    id="consult-insight-webwork"
                    :url="'https://commons.libretexts.org/insight/webwork-techniques'"
                    :formatting-class="''"
                  />
                  or visit <a href="https://webwork.maa.org/wiki/Authors"
                              target="_blank"
                >https://webwork.maa.org/wiki/Authors</a>.
                </div>
                <b-row class="align-items-center mb-2">
                  <b-col cols="auto">
                    <input
                      ref="webworkFileInput"
                      type="file"
                      accept="image/*"
                      style="display:none"
                      @change="onWebworkFileChange"
                    >
                    <b-button size="sm" variant="outline-secondary" @click="$refs.webworkFileInput.click()">
                      Choose Image
                    </b-button>
                  </b-col>
                  <b-col cols="auto">
                    <b-button
                      variant="info"
                      size="sm"
                      :disabled="!webworkUploadFile || webworkUploading"
                      @click="uploadWebworkAttachmentViaPresignedUrl"
                    >
                      <span v-if="webworkUploading"><b-spinner small type="grow"/> Uploading...</span>
                      <span v-else>Upload Image</span>
                    </b-button>
                  </b-col>
                  <b-col v-if="webworkUploadFilename" cols="auto" class="text-muted">
                    {{ webworkUploadFilename }}
                  </b-col>
                </b-row>
                <b-row v-if="webworkUploading" class="mb-2">
                  <b-col cols="6">
                    <b-progress :value="webworkUploadProgress" max="100" show-progress animated/>
                  </b-col>
                </b-row>
                <b-row v-if="webworkAttachments">
                  <ul>
                    <li v-for="(webworkAttachment, webworkAttachmentIndex) in webworkAttachments"
                        :key="`webwork-attachment-${webworkAttachmentIndex}`"
                    >
                      {{ webworkAttachment.filename }}
                      <span class="text-primary"
                            @click="setWebworkAttachmentOptions(webworkAttachment);$bvModal.show('modal-webwork-image-options')"
                      ><font-awesome-icon
                        :icon="copyIcon"
                      /></span>
                      <b-icon-trash @click="confirmDeleteWebworkAttachment(webworkAttachment)"/>
                    </li>
                  </ul>
                </b-row>
                <WebworkMacroPickerModal
                  :current-code="questionForm.webwork_code || ''"
                  @insert="insertMacroLoadStatement"
                />

                <div class="d-flex justify-content-between align-items-center mb-2">
                  <b-button
                    size="sm"
                    variant="outline-primary"
                    @click="$bvModal.show('modal-webwork-macro-picker')"
                  >
                    <b-icon icon="plus-circle" class="mr-1"/>
                    Add Macro
                  </b-button>
                  <a v-if="questionForm.id && initiallyWebworkQuestion"
                     class="btn btn-sm btn-outline-primary link-outline-primary-btn"
                     :href="`/api/questions/export-webwork-code/${questionForm.id}`"
                  >
                    <div style="margin-top:3px">Export Code</div>
                  </a>
                </div>

                <CodeMirrorEditor
                  :key="`webwork-code-${webworkCodeKey}`"
                  v-model="questionForm.webwork_code"
                  :is-invalid="questionForm.errors.has('webwork_code')"
                  @input="questionForm.errors.clear('webwork_code')"
                />
                <has-error :form="questionForm" field="webwork_code"/>
              </div>
            </div>
          </b-card>
        </b-card>
      </b-tab>
      <b-tab id="accessibility-alternatives"
             title="Accessibility Alternatives"
             :title-link-class="getTabClass('accessibility-alternatives')"
      >
        <b-card border-variant="primary"
                class="mb-3"
        >
          <p>An accessible HTML block alternative or an accessible auto-graded version of the question.</p>
          <div v-if="questionForm.question_type !== 'assessment'">
            <b-alert variant="info" show>
              Accessibility alternatives are appropriate for questions which are
              assessments.
            </b-alert>
          </div>
          <div v-else>
            <b-card border-variant="primary"
                    class="mb-3"
            >
              <b-form-group
                label-for="HTML Block Alternative"
              >
                <template v-slot:label>
                  <span style="cursor: pointer;" @click="toggleExpanded ('text_question')">
                    HTML Block Alternative

                    <QuestionCircleTooltip id="text-question-tooltip"/>
                    <b-tooltip target="text-question-tooltip"
                               delay="250"
                               triggers="hover focus"
                    >
                      You can optionally create an open-ended text version of your question which may be useful if you are
                      using one of the
                      non-native auto-graded technologies.
                    </b-tooltip>
                    <font-awesome-icon v-if="!editorGroups.find(group => group.id === 'text_question').expanded"
                                       :icon="caretRightIcon" size="lg"
                    />
                    <font-awesome-icon v-if="editorGroups.find(group => group.id === 'text_question').expanded"
                                       :icon="caretDownIcon" size="lg"
                    /></span>
                </template>
                <ckeditor
                  v-show="editorGroups.find(group => group.id === 'text_question').expanded"
                  id="Open-Ended Alternative"
                  v-model="questionForm.text_question"
                  tabindex="0"
                  :config="richEditorConfig"
                  @namespaceloaded="onCKEditorNamespaceLoaded"
                  @ready="handleFixCKEditor()"
                />
              </b-form-group>
            </b-card>
            <b-card border-variant="primary"
                    class="mb-3"
            >
              <b-form-group
                label-cols-sm="5"
                label-cols-lg="4"
                label-for="a11y_auto_graded_question_id"
              >
                <template v-slot:label>
                  Auto-Graded Alternative
                  <QuestionCircleTooltip id="a11y-auto-graded-tooltip"/>
                  <b-tooltip target="a11y-auto-graded-tooltip"
                             delay="250"
                             triggers="hover focus"
                  >
                    You can optionally provide a secondary accessible auto-graded question which can be shown on a
                    per-student basis.
                    Please provide an ADAPT ID of the form {assignment ID}-{question ID} or {question ID}
                  </b-tooltip>
                </template>
              </b-form-group>
              <b-form-group>
                <b-modal id="modal-view-a11y-auto-graded-question"
                         title="Auto-Graded Alternative"
                         no-close-on-backdrop
                         size="lg"
                >
                  <ViewQuestions :key="`question-to-view-${a11yAutoGradedAlternativeQuestionId}`"
                                 :question-ids-to-view="[a11yAutoGradedAlternativeQuestionId]"
                  />
                  <template #modal-footer>
                    <b-button
                      variant="primary"
                      size="sm"
                      class="float-right"
                      @click="$bvModal.hide('modal-view-a11y-auto-graded-question')"
                    >
                      OK
                    </b-button>
                  </template>
                </b-modal>
                <b-form-row>
                  <b-form-input
                    id="a11y_auto_graded_question_id"
                    v-model="questionForm.a11y_auto_graded_question_id"
                    style="width: 200px"
                    size="sm"
                    type="text"
                    :class="{ 'is-invalid': questionForm.errors.has('a11y_auto_graded_question_id')}"
                    @keydown="questionForm.errors.clear('a11y_auto_graded_question_id')"
                  />
                  <has-error :form="questionForm" field="a11y_auto_graded_question_id"/>
                  <b-button size="sm"
                            class="ml-2"
                            variant="primary"
                            @click="showA11yAutoGradedQuestion"
                  >
                    View
                  </b-button>
                </b-form-row>
              </b-form-group>
            </b-card>
          </div>
        </b-card>
      </b-tab>
      <b-tab id="secondary-content"
             title="Secondary Content"
             :title-link-class="getTabClass('secondary-content')"
      >
        <b-card
          border-variant="primary"
          class="mb-3"
        >
          <p>
            An answer/solution to the question, and a hint for students may
            be optionally associated with the question.
          </p>
          <CKEditorFileToLinkUploader v-if="activeTabIndex === 3"
                                      :upload-file-type="'secondary-content'"
                                      :tutorial-video-src="'https://customer-9mlff0qha6p39qdq.cloudflarestream.com/7fa7a91d78df24a66bf62c3e766d5058/iframe?poster=https%3A%2F%2Fcustomer-9mlff0qha6p39qdq.cloudflarestream.com%2F7fa7a91d78df24a66bf62c3e766d5058%2Fthumbnails%2Fthumbnail.jpg%3Ftime%3D%26height%3D600'"
                                      :modal-title="'Adding Files to Secondary Content'"
          />
          <div
            v-for="editorGroup in editorGroups.filter(group => !['technology','a11y_auto_graded_question_id','non_technology_text','text_question','notes'].includes(group.id))"
            :key="editorGroup.id"
          >
            <div v-if="editorGroup.id === 'solution_html' &&
  questionForm.webwork_code &&
  (questionForm.webwork_code.includes('BEGIN_PGML_SOLUTION') ||
   questionForm.webwork_code.includes('BEGIN_SOLUTION')) &&
  !/#\s*BEGIN_PGML_SOLUTION/.test(questionForm.webwork_code) &&
  !/#\s*BEGIN_SOLUTION/.test(questionForm.webwork_code)"
            >
              <b-alert show variant="info">
                Since you have a solution embedded in your weBWork code, the solution below will be ignored.
              </b-alert>
            </div>
            <b-form-group
              v-if="questionForm.question_type === 'assessment' || editorGroup.id==='notes'"
              :label-for="editorGroup.label"
            >
              <span style="cursor: pointer;" @click="toggleExpanded (editorGroup.id)">
                {{ editorGroup.label }}
                <span v-if="editorGroup.label === 'Answer'">
                  <span v-if="!isNativeQti()"> <QuestionCircleTooltip id="answer-tooltip"/>
                    <b-tooltip target="answer-tooltip"
                               delay="250"
                               triggers="hover focus"
                    >
                      The answer to the question.  Answers are optional.
                    </b-tooltip>
                  </span>
                </span>
                <span v-if="editorGroup.label === 'Solution'"><QuestionCircleTooltip id="solution-tooltip"/>
                  <b-tooltip target="solution-tooltip"
                             delay="250"
                             triggers="hover focus"
                  >
                    A more detailed solution to the question. Solutions are optional.
                  </b-tooltip>
                </span>
                <span v-if="editorGroup.label === 'Hint'"><QuestionCircleTooltip id="hint-tooltip"/>
                  <b-tooltip target="hint-tooltip"
                             delay="250"
                             triggers="hover focus"
                  >
                    Hints can be provided to students within assignments. Hints are optional.
                  </b-tooltip>
                </span>
                <span v-show="!(isNativeQti() && editorGroup.id === 'answer_html')">
                  <font-awesome-icon
                    v-if="!editorGroups.find(group => group.id === editorGroup.id).expanded"
                    :icon="caretRightIcon" size="lg"
                  />
                  <font-awesome-icon v-if="editorGroups.find(group => group.id === editorGroup.id).expanded"
                                     :icon="caretDownIcon" size="lg"
                  />
                </span>
              </span>
              <div v-if="isNativeQti() && editorGroup.id === 'answer_html'">
                <b-alert show variant="info">
                  Native question types already have the answer built into the question creation process.
                </b-alert>
              </div>
              <ckeditor
                v-show="editorGroups.find(group => group.id === editorGroup.id).expanded && !(isNativeQti() && editorGroup.id === 'answer_html')"
                :id="editorGroup.label"
                v-model="questionForm[editorGroup.id]"
                tabindex="0"
                :config="richEditorConfig"
                @namespaceloaded="onCKEditorNamespaceLoaded"
                @ready="handleFixCKEditor()"
              />
            </b-form-group>
          </div>
        </b-card>
      </b-tab>
      <b-tab id="private-notes"
             title="Private Notes"
             :title-link-class="getTabClass('private-notes')"
      >
        <b-card border-variant="primary"
                class="mb-3"
        >
          <b-form-group>
            <p>
              Notes are a way for the creator of the question to associate additional information with the
              question. Notes are optional and students
              will never see this information.
            </p>
            <ckeditor
              id="notes"
              v-model="questionForm.notes"
              tabindex="0"
              :config="richEditorConfig"
              @namespaceloaded="onCKEditorNamespaceLoaded"
              @ready="handleFixCKEditor()"
            />
          </b-form-group>
        </b-card>
      </b-tab>
      <b-tab id="rubric"
             title="Rubric"
             :title-link-class="getTabClass('rubric')"
      >
        <b-card
          border-variant="primary"
          class="mb-3"
        >
          <p>Rubrics are associated with each question but may be edited within an assignment context.</p>
          <RubricProperties
            :key="`rubric-properties-${isEditRubric}`"
            :errors="questionForm.errors.errors"
            :rubric-info="{'rubric' : questionForm.rubric}"
            :rubric-properties-question-form-errors="rubricPropertiesQuestionFormErrors"
            :is-edit="isEditRubric"
            :is-template="false"
            @setKeyValue="setKeyValue"
          />
          <template v-slot:cell(criterion)="data">
            {{ data.item.title }}
            <QuestionCircleTooltip v-show="data.item.description"
                                   :id="`rubric-item-tooltip-${data.item.title}`"
            />
            <b-tooltip :target="`rubric-item-tooltip-${data.item.title}`"
                       delay="250"
                       triggers="hover focus"
            >
              {{ data.item.description }}
            </b-tooltip>
          </template>
        </b-card>
      </b-tab>
    </b-tabs>
    <span class="float-right">
      <b-button v-if="isEdit"
                size="sm"
                @click="$bvModal.hide(`modal-edit-question-${questionToEdit.id}`)"
      >
        Cancel</b-button>
      <b-button v-if="isLocalMe && questionForm.technology === 'qti' && jsonShown" size="sm"
                @click="jsonShown = false"
      >
        Hide json
      </b-button>
      <b-button v-if="false && questionForm.technology=== 'qti' && !jsonShown" size="sm"
                @click="jsonShown = true"
      >
        Show json
      </b-button>
      <b-button id="preview-question"
                size="sm"
                variant="info"
                @click="previewQuestion"
      >
        <span v-if="processingPreview"><b-spinner small type="grow"/> </span>
        Preview
      </b-button>

      <b-button
        v-if="!savingQuestion"
        id="save-question"
        size="sm"
        variant="primary"
        @click="checkedOpenEndedSubmissionType = false;initSaveQuestion()"
      >Save</b-button>
    </span>
    <span v-if="savingQuestion">
      <b-spinner small type="grow"/>
      Saving...
    </span>
    <b-container v-if="jsonShown" class="pt-4 mt-4">
      <b-row>{{ qtiJson }}</b-row>
    </b-container>
  </div>
</template>

<script>
import Autocomplete from '@trevoreyre/autocomplete-vue'
import '@trevoreyre/autocomplete-vue/dist/style.css'
import AccountingMultiPartComputation from './accounting/AccountingMultiPartComputation.vue'
import CodeMirrorEditor from '~/components/CodeMirrorEditor'
import QuestionMediaUpload from '~/components/QuestionMediaUpload.vue'
import CKEditorFileToLinkUploader from '~/components/CKEditorFileToLinkUploader.vue'
import { doCopy } from '~/helpers/Copy'
import AllFormErrors from '~/components/AllFormErrors'
import ErrorMessage from '~/components/ErrorMessage'
import { fixInvalid } from '~/helpers/accessibility/FixInvalid'
import Form from 'vform/src'
import { fixCKEditor } from '~/helpers/accessibility/fixCKEditor'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import CKEditor from 'ckeditor4-vue'
import { mapGetters } from 'vuex'
import { defaultLicenseVersionOptions, licenseOptions, updateLicenseVersions } from '~/helpers/Licenses'
import ViewQuestions from '~/components/ViewQuestions'
import SavedQuestionsFolders from '~/components/SavedQuestionsFolders'
import QtiJsonQuestionViewer from '~/components/QtiJsonQuestionViewer'
import { v4 as uuidv4 } from 'uuid'
import { getLearningOutcomes, subjectOptions } from '~/helpers/LearningOutcomes'
import 'vue-select/dist/vue-select.css'
import SolutionFileHtml from '~/components/SolutionFileHtml'
import $ from 'jquery'

import axios from 'axios'
import MultipleAnswersAdvanced from './MultipleAnswersAdvanced.vue'
import FillInTheBlank from './FillInTheBlank'
import MatrixMultipleResponse from './nursing/MatrixMultipleResponse'
import MultipleResponseGrouping from './nursing/MultipleResponseGrouping'
import MultipleChoiceTrueFalse from './MultipleChoiceTrueFalse'
import BowTie from './nursing/BowTie'
import MultipleResponseSelectAllThatApplyOrSelectN from './nursing/MultipleResponseSelectAllThatApplyOrSelectN'
import DropDownTable from './nursing/DropDownTable'
import DragAndDropCloze from './nursing/DragAndDropCloze'
import MatrixMultipleChoice from './nursing/MatrixMultipleChoice'
import SelectChoiceDropDownRationale from './nursing/SelectChoiceDropDownRationale'
import HighlightText from './nursing/HighlightText'
import HighlightTable from './nursing/HighlightTable'
import Matching from './Matching'
import SingleNumerical from './SingleNumerical'
import MultiNumerical from './MultiNumerical'
import MultipleAnswers from './MultipleAnswers'
import { faCopy } from '@fortawesome/free-regular-svg-icons'
import FrameworkAligner from '../FrameworkAligner'
import DropDownRationaleTriad from './nursing/DropDownRationaleTriad.vue'
import QuestionRevisionDifferences from '../QuestionRevisionDifferences.vue'
import Sketcher from './Sketcher.vue'
import { updateModalToggleIndex } from '../../helpers/accessibility/fixCKEditor'
import QuestionCircleTooltipModal from '../QuestionCircleTooltipModal.vue'
import ConsultInsight from '../ConsultInsight.vue'
import RubricProperties from '../RubricProperties.vue'
import { faCaretDown, faCaretRight } from '@fortawesome/free-solid-svg-icons'
import {
  canEdit,
  getQuestionSectionIdOptions,
  handleAddEditQuestionSubjectChapterSection,
  initAddEditDeleteQuestionSubjectChapterSection,
  openEndedSubmissionTypeOptions,
  responseFormatOptions
} from '~/helpers/Questions'
import StructureImageUploader from '../StructureImageUploader.vue'
import { capitalize, getQuestionChapterIdOptions, getQuestionSubjectIdOptions } from '../../helpers/Questions'
import ThreeDModel from './ThreeDModel.vue'
import AccountingJournalEntry from './accounting/AccountingJournalEntry.vue'
import AccountingReport from './accounting/AccountingReport.vue'
import Flashcard from './Flashcard.vue'
import WebworkMacroPickerModal from '../WebworkMacroPickerModal.vue'

const parameters3DModel = {
  modelID: '',
  BGImage: '',
  BGColor: 'd3d3d3',
  mode: '',
  annotations: '',
  modelOffset: '0,0,0',
  cameraOffset: '2.25',
  selectionColor: '0058E6',
  panel: 'no',
  autospin: 'no',
  STLmatCol: 'ffffff',
  hideDistance: '5'
}
const defaultQuestionForm = {
  question_type: 'assessment',
  question_subject_id: null,
  public: '0',
  title: '',
  description: '',
  learning_outcomes: [],
  attachments: [],
  media_uploads: [],
  author: '',
  tags: [],
  technology: 'text',
  technology_id: '',
  non_technology_text: '',
  purpose: '',
  open_ended_submission_type: '0',
  grading_style_id: null,
  rubric_categories: [],
  rubric_name: '',
  rubric_description: '',
  rubric_shown: true,
  rubric_template_save_option: 'do not save as template',
  text_question: null,
  a11y_auto_graded_question_id: null,
  answer_html: null,
  solution_html: null,
  notes: null,
  hint: null,
  license: null,
  license_version: null,
  source_url: ''
}

const multipleResponseRichEditorConfig = {
  toolbar: [
    { name: 'math', items: ['Mathjax'] },
    { name: 'clipboard', items: ['Cut', 'Copy', 'Paste', '-', 'Undo', 'Redo'] },
    {
      name: 'basicstyles',
      items: ['Bold', 'Italic', 'Underline', 'Subscript', 'Superscript']
    },
    { name: 'links', items: ['Link', 'Unlink', 'IFrame', 'Embed'] },
    { name: 'extra', items: ['Source', 'Maximize'] }
  ],
  embed_provider: '//ckeditor.iframe.ly/api/oembed?url={url}&callback={callback}',
  // Configure the Enhanced Image plugin to use classes instead of styles and to disable the
  // resizer (because image size is controlled by widget styles or the image takes maximum
  // 100% of the editor width).
  removeButtons: '',
  extraPlugins: 'mathjax,embed,dialog,contextmenu',
  mathJaxLib: 'https://cdnjs.cloudflare.com/ajax/libs/mathjax/2.7.4/MathJax.js?config=TeX-AMS_HTML',
  height: 50,
  allowedContent: true,
  disableNativeSpellChecker: false
}

const matchingRichEditorConfig = {
  toolbar: [
    { name: 'image', items: ['Image'] },
    { name: 'math', items: ['Mathjax'] },
    { name: 'clipboard', items: ['Cut', 'Copy', 'Paste', '-', 'Undo', 'Redo'] },
    {
      name: 'basicstyles',
      items: ['Bold', 'Italic', 'Underline', 'Subscript', 'Superscript', 'SpecialChar']
    },
    { name: 'links', items: ['Link', 'Unlink', 'IFrame', 'Embed'] },
    { name: 'extra', items: ['Source', 'Maximize'] }
  ],
  embed_provider: '//ckeditor.iframe.ly/api/oembed?url={url}&callback={callback}',
  // Configure the Enhanced Image plugin to use classes instead of styles and to disable the
  // resizer (because image size is controlled by widget styles or the image takes maximum
  // 100% of the editor width).
  removeButtons: '',
  extraPlugins: 'mathjax,embed,dialog,image2,contextmenu,autogrow',
  image2_alignClasses: ['image-align-left', 'image-align-center', 'image-align-right'],
  image2_altRequired: true,
  mathJaxLib: 'https://cdnjs.cloudflare.com/ajax/libs/mathjax/2.7.4/MathJax.js?config=TeX-AMS_HTML',
  autoGrow_minHeight: 75,
  filebrowserUploadUrl: '/api/ckeditor/upload',
  filebrowserUploadMethod: 'form',
  allowedContent: true,
  disableNativeSpellChecker: false
}

const simpleChoiceFeedbackConfig = JSON.parse(JSON.stringify(matchingRichEditorConfig))
const simpleChoiceConfig = JSON.parse(JSON.stringify(matchingRichEditorConfig))
const threeDModelRichEditorConfig = JSON.parse(JSON.stringify(matchingRichEditorConfig))

const richEditorConfig = {
  toolbar: [
    { name: 'image', items: ['Image'] },
    { name: 'math', items: ['Mathjax'] },
    { name: 'clipboard', items: ['Cut', 'Copy', 'Paste', '-', 'Undo', 'Redo'] },
    {
      name: 'basicstyles',
      items: ['Bold', 'Italic', 'Underline', 'Subscript', 'Superscript', '-', 'CopyFormatting', 'RemoveFormat']
    },
    {
      name: 'paragraph',
      items: ['BulletedList', 'NumberedList', '-', 'Outdent', 'Indent', '-', 'JustifyLeft', 'JustifyCenter', 'JustifyRight', 'JustifyBlock']
    },
    { name: 'links', items: ['Link', 'Unlink', 'IFrame', 'Embed'] },
    { name: 'insert', items: ['Table', 'HorizontalRule', 'Smiley', 'SpecialChar'] },
    { name: 'styles', items: ['Styles', 'Format', 'Font', 'FontSize'] },
    { name: 'colors', items: ['TextColor', 'BGColor'] },
    { name: 'extra', items: ['Source', 'Maximize'] }
  ],
  embed_provider: '//ckeditor.iframe.ly/api/oembed?url={url}&callback={callback}',
  // Configure the Enhanced Image plugin to use classes instead of styles and to disable the
  // resizer (because image size is controlled by widget styles or the image takes maximum
  // 100% of the editor width).
  image2_alignClasses: ['image-align-left', 'image-align-center', 'image-align-right'],
  image2_altRequired: true,
  removeButtons: '',
  extraPlugins: 'mathjax,embed,dialog,contextmenu,liststyle,image2,autogrow',
  mathJaxLib: 'https://cdnjs.cloudflare.com/ajax/libs/mathjax/2.7.4/MathJax.js?config=TeX-AMS_HTML',
  filebrowserUploadUrl: '/api/ckeditor/upload',
  filebrowserUploadMethod: 'form',
  format_tags: 'p;h2;h3;pre',
  allowedContent: true,
  disableNativeSpellChecker: false
}

let shorterRichEditorConfig = JSON.parse(JSON.stringify(richEditorConfig))
shorterRichEditorConfig.autoGrow_minHeight = 100

const simpleChoiceJson = {
  questionType: 'multiple_choice',
  prompt: '',
  simpleChoice: {}
}

const textEntryInteractionJson = {
  questionType: 'fill_in_the_blank',
  'responseDeclaration': {
    'correctResponse': {
      'value': ''
    }
  },
  'itemBody': {}
}

export default {
  name: 'CreateQuestion',
  components: {
    Autocomplete,
    AccountingMultiPartComputation,
    WebworkMacroPickerModal,
    CodeMirrorEditor,
    Flashcard,
    AccountingReport,
    AccountingJournalEntry,
    CKEditorFileToLinkUploader,
    ThreeDModel,
    StructureImageUploader,
    MultipleAnswersAdvanced,
    RubricProperties,
    ConsultInsight,
    QuestionCircleTooltipModal,
    Sketcher,
    QuestionMediaUpload,
    QuestionRevisionDifferences,
    DropDownRationaleTriad,
    ErrorMessage,
    FrameworkAligner,
    MultipleChoiceTrueFalse,
    MultipleAnswers,
    SingleNumerical,
    MultiNumerical,
    FillInTheBlank,
    Matching,
    HighlightTable,
    HighlightText,
    SelectChoiceDropDownRationale,
    MatrixMultipleChoice,
    DragAndDropCloze,
    DropDownTable,
    MultipleResponseSelectAllThatApplyOrSelectN,
    MatrixMultipleResponse,
    MultipleResponseGrouping,
    BowTie,
    FontAwesomeIcon,
    ckeditor: CKEditor.component,
    AllFormErrors,
    ViewQuestions,
    SavedQuestionsFolders,
    QtiJsonQuestionViewer
  },
  props: {
    assignmentId: {
      type: Number,
      default: 0
    },
    questionMediaUploadId: {
      type: Number,
      default: 0
    },
    modalId: {
      type: String,
      default: ''
    },
    questionToEdit: {
      type: Object,
      default: () => {
      }
    },
    parentGetMyQuestions: {
      type: Function,
      default: () => {
      }
    },
    questionExistsInOwnAssignment: {
      type: Boolean,
      default: false
    },
    questionExistsInAnotherInstructorsAssignment: {
      type: Boolean,
      default: false
    }
  },
  data: () => ({
    allQuestionEditors: [],
    selectedOwnerId: null,
    ownerFolderOptions: [],
    webworkUploadFilename: '',
    webworkUploadFile: null,
    webworkUploadProgress: 0,
    webworkUploading: false,
    webworkCodeKey: 0,
    isLoadingEdit: false,
    attachmentToDelete: {},
    attachmentsFields: [
      {
        key: 'original_filename',
        label: 'Filename'
      },
      'actions'
    ],
    temporaryUrl: '',
    filename: '',
    preSignedURL: '',
    uploadProgress: 0,
    selectedFile: null,
    threeDModelSolutionStructureErrors: '',
    threeDModelParameterErrors: [],
    questionSubjectChapterSectionAction: '',
    questionSubjectChapterSectionForm: new Form({}),
    questionSubjectChapterSectionToAddEditLevel: '',
    questionSubjectChapterSectionToEditDeleteName: '',
    questionChapterIdOptions: [{ value: null, text: 'Choose a chapter' }],
    questionSubjectIdOptions: [{ value: null, text: 'Choose a subject' }],
    questionSectionIdOptions: [{ value: null, text: 'Choose a section' }],
    checkedOpenEndedSubmissionType: false,
    openEndedSubmissionTypeOptions: openEndedSubmissionTypeOptions,
    parameters3DModel: parameters3DModel,
    smiles: '',
    h5pUrl: 'https://studio.libretexts.org/node/add/h5p',
    imathASUrl: 'https://imathas.libretexts.org/imathas/course/moddataset.php',
    initSketcherReload: false,
    multipleAnswersAdvancedKey: 0,
    sketcherType: 'default',
    updatingStructure: false,
    isWebworkDownloadOnly: false,
    activeTabIndex: 0,
    sketcherTabClicked: false,
    loaded: false,
    tabErrors: {
      'properties': false,
      'primary-content': false,
      'accessibility-alternatives': false,
      'secondary-content': false,
      'rubric': false,
      'private-notes': false
    },
    rubricPropertiesKey: 0,
    rubricPropertiesQuestionFormErrors: {},
    isEditRubric: false,
    rubric: {},
    activeQuestionMediaUpload: {},
    discussItTextForm: new Form({
      text: '',
      description: ''
    }),
    discussItNumPages: 1,
    discussItTemporaryUrl: '',
    previewingQuestion: false,
    solutionStructure: {},
    receivedStructure: false,
    molViewJson: {},
    questionMediaUploadKey: 0,
    a11yAutoGradedAlternativeQuestionId: 0,
    initiallyWebworkQuestion: false,
    switchingType: false,
    changeResizeType: false,
    initResizeHeight: 0,
    initResizeWidth: 0,
    resizeWidth: 100,
    resizeHeight: 100,
    resizeImageBy: 'percentage',
    maintainAspectRatio: true,
    diffsShown: true,
    mathJaxRendered: false,
    revision1: {},
    revision2: {},
    questionRevisionDifferencesKey: 0,
    revisionAction: '',
    powerUser: false,
    differences: [],
    revision1Id: null,
    revision2Id: null,
    revisionOptions: [],
    revision: 0,
    multipleChoiceTrueFalseKey: 0,
    gradingStyleOptions: [],
    showFolderOptions: true,
    ckeditorKeyDown: false,
    nativeType: 'basic',
    fullyMounted: false,
    webworkAttachmentToDelete: '',
    webworkImageOptions: {
      filename: '',
      width: '',
      height: '',
      tex_size: '',
      extras_html_tags: ''
    },
    webworkAttachments: [],
    sessionIdentifier: '',
    errorMessages: [],
    savingQuestion: false,
    frameworkItemSyncQuestion: { 'descriptors': [], 'levels': [] },
    copyIcon: faCopy,
    copyHistoryQuestionId: null,
    simpleChoiceToRemove: {},
    qtiAnswerJson: '',
    nursingQuestions: ['bow_tie',
      'multiple_response_select_all_that_apply',
      'multiple_response_select_n',
      'matrix_multiple_response',
      'multiple_response_grouping',
      'drop_down_table',
      'drag_and_drop_cloze',
      'matrix_multiple_choice',
      'bow_tie',
      'highlight_text',
      'highlight_table',
      'drop_down_rationale'],
    existingQuestionFormTechnology: 'text',
    nursing: false,
    currentQuestionEditor: '',
    learningOutcome: '',
    subject: null,
    subjectOptions: subjectOptions,
    learningOutcomeOptions: [],
    imgNeedsAltSrc: '',
    originalPreexistingWebworkCode: '',
    updatingTempalteWithPreexistingWebworkFilePath: false,
    preExistingWebworkFilePath: '',
    showPreexistingWebworkFilePath: false,
    generalFeedbacks: [{
      key: 'correct',
      id: 'correct-response-feedback',
      label: 'Correct Response',
      editorShown: false
    },
      {
        key: 'incorrect',
        id: 'incorrect-response-feedback',
        label: 'Incorrect Response',
        editorShown: false
      },
      {
        key: 'any',
        id: 'any-response-feedback',
        label: 'Any Response',
        editorShown: false
      }
    ],
    webworkTemplate: null,
    webworkTemplateOptions: [],
    processingPreview: false,
    webworkEditorShown: false,
    multipleChoiceGeneralFeedbacks: [{
      key: 'correct',
      id: 'correct-response-feedback',
      label: 'Correct Response',
      editorShown: false
    },
      {
        key: 'incorrect',
        id: 'incorrect-response-feedback',
        label: 'Incorrect Response',
        editorShown: false
      },
      {
        key: 'any',
        id: 'any-response-feedback',
        label: 'Any Response',
        editorShown: false
      }
    ],
    simpleChoiceFeedbackConfig: simpleChoiceFeedbackConfig,
    jsonShown: false,
    qtiJsonQuestionViewerKey: 0,
    showQtiAnswer: false,
    questionFormTechnology: 'text',
    qtiQuestionType: 'multiple_choice',
    qtiPrompt: '',
    correctResponse: '',
    qtiJson: {},
    sourceExpanded: false,
    caretDownIcon: faCaretDown,
    caretRightIcon: faCaretRight,
    newAutoGradedTechnology: null,
    createA11yAutoGradedTechnology: null,
    savedQuestionsFolderKey: 0,
    questionToView: {},
    questionToViewKey: 0,
    questionsFormKey: 0,
    isEdit: false,
    tag: '',
    toggleColors: window.config.toggleColors,
    view: 'basic',
    licenseOptions: licenseOptions,
    defaultLicenseVersionOptions: defaultLicenseVersionOptions,
    licenseVersionOptions: [],
    editorGroups: [
      { id: 'technology', expanded: false },
      { id: 'non_technology_text', label: 'Open-Ended Content', expanded: false },
      { label: 'Open-Ended Alternative', id: 'text_question', expanded: false },
      { label: 'Auto-Graded Alternative', id: 'a11y_auto_graded_question_id', expanded: false },
      { label: 'Answer', id: 'answer_html', expanded: false },
      { label: 'Solution', id: 'solution_html', expanded: false },
      { label: 'Hint', id: 'hint', expanded: false },
      { label: 'Notes', id: 'notes', expanded: false }
    ],
    questionForm: new Form(defaultQuestionForm),
    allFormErrors: [],
    responseFormatOptions: responseFormatOptions,
    existingAutoGradedTechnologyOptions: [
      { value: 'text', text: 'None' },
      { value: 'webwork', text: 'WeBWorK' },
      { value: 'h5p', text: 'H5P' },
      { value: 'imathas', text: 'IMathAS' }
    ],
    a11yAutoGradedTechnologyOptions: [
      { value: null, text: 'None' },
      { value: 'qti', text: 'Native' },
      { value: 'webwork', text: 'WeBWorK' },
      { value: 'h5p', text: 'H5P' },
      { value: 'imathas', text: 'IMathAS' }
    ],
    richEditorConfig: richEditorConfig,
    shorterRichEditorConfig: shorterRichEditorConfig,
    multipleResponseRichEditorConfig: multipleResponseRichEditorConfig,
    simpleChoiceConfig: simpleChoiceConfig,
    matchingRichEditorConfig: matchingRichEditorConfig,
    threeDModelRichEditorConfig: threeDModelRichEditorConfig
  }),
  watch: {
    activeTabIndex: async function (value) {
      if (value === 1) {
        this.moveSketcherToTab()
      } else {
        await this.moveSketcherFromTab(this.nativeType === 'sketcher')
      }
    },
    nativeType: async function (value) {
      if (value === 'sketcher') {
        this.moveSketcherToTab()
      } else {
        await this.moveSketcherFromTab(false)
      }
    }
  },
  computed: {
    ...mapGetters({
      user: 'auth/user'
    }),
    isLocalMe: () => window.config.isAdmin && window.location.hostname === 'local.adapt',
    isAdmin: () => window.config.isAdmin
  },
  created () {
    this.getLearningOutcomes = getLearningOutcomes
    window.addEventListener('message', this.receiveMessage, false)
    // window.addEventListener('keyup', this.updateTabInvalids)
  },
  beforeDestroy () {
    window.removeEventListener('keydown', this.hotKeys)
    // window.removeEventListener('keyup', this.updateTabInvalids)
  },
  updated: function () {
    this.$nextTick(function () {
      this.updateTabInvalids()
    })
    if (this.ckeditorKeyDown) {
      console.log('no update needed')
      return
    }
    this.$nextTick(function () {
      if (this.fullyMounted) {
        if (this.discussItTextForm.text) {
          if (this.discussItTextForm.text.search('<p>&nbsp;</p>') !== -1) {
            this.fixEmptyParagraphs('discuss_it_text')
          } else {
            if (this.discussItTextForm.text.search('<p>&nbsp;</p>') === -1) {
              console.log('discuss_it text paragraphs have been removed')
            }
          }
        }
        if (this.questionForm.non_technology_text) {
          if (this.questionForm.non_technology_text.search('<p>&nbsp;</p>') !== -1) {
            this.fixEmptyParagraphs('non_technology_text')
          } else {
            if (this.questionForm.non_technology_text.search('<p>&nbsp;</p>') === -1) {
              console.log('non-technology paragraphs have been removed')
            }
          }
        }
        if (this.qtiJson.itemBody && typeof this.qtiJson.itemBody === 'string' && this.qtiJson.itemBody.length > 0) {
          if (this.qtiJson.itemBody.search('<p>&nbsp;</p>') !== -1) {
            this.fixEmptyParagraphs('qti_json_item_body')
          } else {
            if (this.qtiJson.itemBody.search('<p>&nbsp;</p>') === -1) {
              console.log('qti json item body paragraphs have been removed')
            }
          }
        }
        if (this.qtiJson.itemBody && typeof this.qtiJson.itemBody.textEntryInteraction === 'string' && this.qtiJson.itemBody.textEntryInteraction.length > 0) {
          if (this.qtiJson.itemBody.textEntryInteraction.search('<p>&nbsp;</p>') !== -1) {
            this.fixEmptyParagraphs('qti_json_text_entry_interaction')
          } else {
            if (this.qtiJson.itemBody.textEntryInteraction.search('<p>&nbsp;</p>') === -1) {
              console.log('qti json item body paragraphs have been removed')
            }
          }
        }

        if (this.qtiJson.prompt) {
          if (this.qtiJson.prompt.search('<p>&nbsp;</p>') !== -1) {
            this.fixEmptyParagraphs('qti_json_prompt')
          } else {
            if (this.qtiJson.prompt.search('<p>&nbsp;</p>') === -1) {
              console.log('qti json prompt paragraphs have been removed')
            }
          }
        }
      }
    })
  },
  async mounted () {
    if (![2, 5].includes(this.user.role)) {
      return false
    }
    await this.getQuestionSubjectIdOptions()
    this.updateTabInvalids()
    // this.questionType = 'native'
    // this.nativeType = 'sketcher'
    // this.qtiQuestionType = 'submit_molecule'

    this.switchingType = false
    this.doCopy = doCopy
    window.addEventListener('keydown', this.hotKeys)
    this.$nextTick(() => {
      // want to add more text to this
      $('#required_text').replaceWith($('<span>' + document.getElementById('required_text').innerText + '</span>'))
      $('#question-editor-tabs__BV_tab_container_').removeClass('mt-3').css('marginTop', '-1px')
    })
    this.updateLicenseVersions = updateLicenseVersions
    console.log(this.questionToEdit)
    this.sessionIdentifier = uuidv4()
    this.webworkAttachments = []
    await this.getGradingStyles()
    await this.getWebworkTemplateOptions()
    if (this.questionToEdit && Object.keys(this.questionToEdit).length !== 0) {
      await this.setQuestionToEdit()
    } else {
      await this.resetQuestionForm('assessment')
      this.initNativeType()
    }
    this.questionForm.source_url = this.questionForm.source_url ? this.questionForm.source_url : window.location.origin
    this.$nextTick(() => {
      this.fullyMounted = true
    })
    if (!this.isEdit) {
      this.$nextTick(() => {
        // this.setToQuestionType('three_d_model_multiple_choice')
        // this.setToQuestionType('accounting_journal_entry')
        // this.setToQuestionType('accounting_report')
        // this.setToQuestionType('flashcard')
        //this.setToQuestionType('accounting_multi_part_computation')
        //this.setToQuestionType('multi_numerical')
      })
    }
  },
  destroyed () {
    if (this.questionToEdit) {
      axios.delete(`/api/current-question-editor/${this.questionToEdit.id}`)
    }
    window.removeEventListener('message', this.receiveMessage)
  },
  methods: {
    searchByOwner (input) {
      if (input.length < 1) return []
      return this.allQuestionEditors
        .filter(e => e.label.toLowerCase().includes(input.toLowerCase()))
        .map(e => e.label)
        .sort()
    },

    onOwnerSelect (selectedLabel) {
      if (!selectedLabel) return
      const match = this.allQuestionEditors.find(e => e.label === selectedLabel)
      if (match) {
        this.onOwnerChange(match.value)
      }
    },
    async getAllQuestionEditors () {
      try {
        const { data } = await axios.get('/api/user/question-editors/1')
        if (data.type === 'error') {
          this.$noty.error(data.message)
          return
        }
        // Prepend the current owner in case they're excluded from the list
        // (endpoint excludes the requesting admin, not the question owner)
        const currentOwner = {
          value: this.questionToEdit.question_editor_user_id,
          label: this.questionToEdit.question_editor_name
        }
        const alreadyIncluded = data.question_editors.some(
          e => e.value === currentOwner.value
        )
        this.allQuestionEditors = alreadyIncluded
          ? data.question_editors
          : [currentOwner, ...data.question_editors]
      } catch (error) {
        this.$noty.error(error.message)
      }
    },

    async getAdminFolderOptions (userId) {
      try {
        const { data } = await axios.get(
          `/api/saved-questions-folders/options/my-questions-folders/${userId}`
        )
        if (data.type === 'error') {
          this.$noty.error(data.message)
          return
        }
        this.ownerFolderOptions = [
          { value: null, text: 'Choose a folder' },
          ...data.my_questions_folders.map(f => ({ value: f.id, text: f.name }))
        ]
      } catch (error) {
        this.$noty.error(error.message)
      }
    },

    async onOwnerChange (newOwnerId) {
      this.questionForm.question_editor_user_id = newOwnerId
      this.questionForm.folder_id = null
      await this.getAdminFolderOptions(newOwnerId)
    },
    insertMacroLoadStatement (statement) {
      let code = this.questionForm.webwork_code || ''

      // Extract just the macro filename from the loadMacros("...") statement
      const macroMatch = statement.match(/loadMacros\(\s*"([^"]+)"\s*\)/)
      const macroFile = macroMatch ? macroMatch[1] : null

      if (macroFile) {
        // Check if there's already a loadMacros block
        const existingLoadMacros = code.match(/loadMacros\s*\(([^)]+)\)/)
        if (existingLoadMacros) {
          // Add to existing loadMacros list
          const insertion = `,\n  "${macroFile}"`
          code = code.replace(existingLoadMacros[0], existingLoadMacros[0].replace(/\)$/, `${insertion}\n)`))
          this.questionForm.webwork_code = code
          this.webworkCodeKey++
          return
        }
      }

      // No existing loadMacros — insert after DOCUMENT(); or prepend DOCUMENT(); if missing
      if (code.includes('DOCUMENT();')) {
        code = code.replace('DOCUMENT();', `DOCUMENT();\n${statement}`)
      } else {
        code = `DOCUMENT();\n${statement}\n` + code
      }

      this.questionForm.webwork_code = code
      this.webworkCodeKey++
    },
    capitalize,
    getQuestionSubjectIdOptions,
    getQuestionChapterIdOptions,
    handleAddEditQuestionSubjectChapterSection,
    initAddEditDeleteQuestionSubjectChapterSection,
    canEdit,
    getQuestionSectionIdOptions,
    async handleFlashcardFileUpload ({ side, mediaType, file }) {
      try {
        const { data } = await axios.post('/api/s3/pre-signed-url', {
          upload_file_type: 'question-attachment',
          file_name: file.name
        })
        if (data.type === 'error') {
          this.$noty.error(data.message)
          return
        }
        await this.uploadWithProgress(file, data.preSignedURL, { skipAttachment: true })
        this.$refs.flashcard.updateMediaUrl(side, mediaType, data.s3_key, data.temporary_url)
      } catch (err) {
        this.$noty.error('File upload failed: ' + err.message)
      }
    },
    isNativeQti () {
      try {
        return this.questionForm.technology === 'qti' &&
          JSON.parse(this.questionForm.qti_json).questionType !== 'forge'
      } catch {
        return false
      }
    },
    initDeleteAttachment (attachment) {
      this.attachmentToDelete = attachment
      this.$bvModal.show('modal-confirm-delete-attachment')
    },
    async deleteAttachment () {
      this.questionForm.attachments = this.questionForm.attachments.filter(item => item.s3_key !== this.attachmentToDelete.s3_key)
      return
      const attachment = this.attachmentToDelete
      try {
        const { data } = await axios.post(`/api/questions/delete-attachment`, {
          filename: attachment.original_filename,
          s3_key: attachment.s3_key,
          question_id: this.questionToEdit ? this.questionToEdit.id : 0
        })
        if (data.type === 'info') {
          this.questionForm.attachments = this.questionForm.attachments.filter(item => item.s3_key !== attachment.s3_key)
        }
        this.$noty[data.type](data.message)
      } catch (error) {
        this.$noty.error(error.message)
      }
    },
    triggerFileDialog () {
      this.$refs.fileInput.click()
    },
    updateModalToggleIndex,
    onFileChange (e) {
      const file = e.target.files[0]
      if (!file) return
      this.selectedFile = file
      this.getPresignedUrlAndUpload(file)
    },
    async getPresignedUrlAndUpload (file) {
      this.preSignedURL = ''
      try {
        let uploadFileData = {
          upload_file_type: 'question-attachment',
          file_name: file.name
        }
        const { data } = await axios.post('/api/s3/pre-signed-url', uploadFileData)
        if (data.type === 'error') {
          this.$noty.error(data.message)
          return false
        }

        this.preSignedURL = data.preSignedURL
        this.s3_key = data.s3_key
        this.fileName = file.name
        await this.uploadWithProgress(file, this.preSignedURL)
        console.log('Upload complete!')
      } catch (err) {
        console.error('Upload failed:', err)
      }
    },
    async uploadWithProgress (file, url, options = {}) {
      return new Promise((resolve, reject) => {
        const xhr = new XMLHttpRequest()

        xhr.upload.addEventListener('progress', (e) => {
          if (e.lengthComputable) {
            this.uploadProgress = Math.round((e.loaded / e.total) * 100)
            if (this.uploadProgress === 100) {
              this.preSignedURL = ''
            }
          }
        })

        xhr.onload = () => {
          if (xhr.status === 200) {
            if (!options.skipAttachment) {
              if (!Array.isArray(this.questionForm.attachments)) {
                this.questionForm.attachments = []
              }
              this.questionForm.attachments.push({ original_filename: this.fileName, s3_key: this.s3_key })
              this.$forceUpdate()
            }
            resolve()
          } else {
            reject(new Error('Upload failed with status ' + xhr.status))
          }
        }

        xhr.onerror = () => reject(new Error('XHR network error'))
        xhr.open('PUT', url)
        xhr.send(file)
      })
    },
    updateQtiJson (key, value) {
      this.qtiJson[key] = value
      console.error(this.qtiJson)
    },
    convertToMolecule () {
      const iframe = document.querySelector('iframe[src="/api/sketcher/default"]')
      iframe.contentWindow.postMessage({
        method: 'import',
        smiles: this.smiles
      }, '*')
    },
    setAtomsAndBonds (atomsAndBonds) {
      this.questionForm.atoms_and_bonds = atomsAndBonds
    },
    handleSketcherClick () {
      console.error('clicked')
    },
    setMarks () {
      alert('marks set')
    },
    updateStructure () {
      this.initSketcherReload = false
      this.qtiJson.solutionStructure = ''
      this.solutionStructure = {}
      this.questionForm.atoms_and_bonds = []
      this.sketcherType = 'default'
      this.initSketcherReload = true
      this.$bvModal.hide('modal-confirm-update-structure')
      this.$noty.info('The Sketcher has been reset.')
    },
    initUpdateStructure () {
      this.$bvModal.show('modal-confirm-update-structure')
    },
    async updateMarks () {
      await this.setMolecule()
      console.error('Molecule has been set')
      console.error('starting solutions structure')
      console.error(this.qtiJson.solutionStructure)
      console.error(this.questionForm.atoms_and_bonds)
      for (let i = 0; i < this.questionForm.atoms_and_bonds.length; i++) {
        const item = this.questionForm.atoms_and_bonds[i]
        console.error(item)
        for (const keyToUpdate of ['correct', 'incorrect', 'feedback']) {
          console.error('updating: ' + keyToUpdate + ' in ' + item.structuralComponent + 's')
          this.qtiJson.solutionStructure[item.structuralComponent + 's'][item.structuralIndex][keyToUpdate] = item[keyToUpdate]
        }
      }
      console.error('final solutions structure')
      console.error(this.qtiJson.solutionStructure)
      this.multipleAnswersAdvancedKey++
      this.$noty.success('The marks have been updated.')
    },
    async setMolecule () {
      this.receivedStructure = false
      const iframe = document.getElementById('sketcher')
      iframe.contentWindow.postMessage('save', '*')
      await this.handleGetStructure()
      this.sketcherType = 'marker-only'
      console.error('Molecule has been set')
    },
    setToQuestionType (questionType) {
      document.getElementById('primary-content___BV_tab_button__').click()
      switch (questionType) {
        case ('numerical'):
          document.querySelector('input[type="radio"][name="question-type"][value="qti"]').click()
          window.setTimeout(() => {
              document.querySelector('input[type="radio"][name="native-question-type"][value="basic"]').click()
            }
            , 250
          )
          window.setTimeout(() => {
              document.querySelector('input[type="radio"][name="qti-question-type"][value="numerical"]').click()
            }
            , 250
          )
          break
        case ('multi_numerical'):
          document.querySelector('input[type="radio"][name="question-type"][value="qti"]').click()
          window.setTimeout(() => {
              document.querySelector('input[type="radio"][name="native-question-type"][value="basic"]').click()
            }
            , 250
          )
          window.setTimeout(() => {
              document.querySelector('input[type="radio"][name="qti-question-type"][value="multi_numerical"]').click()
            }
            , 250
          )
          break
        case ('accounting_multi_part_computation'):
          document.querySelector('input[type="radio"][name="question-type"][value="qti"]').click()
          window.setTimeout(() => {
              document.querySelector('input[type="radio"][name="native-question-type"][value="accounting"]').click()
            }, 250
          )
          window.setTimeout(() => {
              document.querySelector('input[type="radio"][name="qti-question-type"][value="accounting_multi_part_computation"]').click()
            }, 250
          )
          break
        case ('accounting_report'):
          document.querySelector('input[type="radio"][name="question-type"][value="qti"]').click()
          window.setTimeout(() => {
              document.querySelector('input[type="radio"][name="native-question-type"][value="accounting"]').click()
            }
            , 250
          )
          window.setTimeout(() => {
              document.querySelector('input[type="radio"][name="qti-question-type"][value="accounting_report"]').click()
            }
            , 250
          )
          break
        case ('accounting_journal_entry'):
          document.querySelector('input[type="radio"][name="question-type"][value="qti"]').click()
          window.setTimeout(() => {
              document.querySelector('input[type="radio"][name="native-question-type"][value="accounting"]').click()
            }
            , 250
          )
          window.setTimeout(() => {
              document.querySelector('input[type="radio"][name="qti-question-type"][value="accounting_journal_entry"]').click()
            }
            , 250
          )
          break
        case ('three_d_model_multiple_choice'):
          document.querySelector('input[type="radio"][name="question-type"][value="qti"]').click()
          window.setTimeout(() => {
              document.querySelector('input[type="radio"][name="native-question-type"][value="3d_model"]').click()
            }
            , 250
          )
          window.setTimeout(() => {
              document.querySelector('input[type="radio"][name="qti-question-type"][value="three_d_model_multiple_choice"]').click()
            }
            , 250
          )
          break
        case ('flashcard'):
          document.querySelector('input[type="radio"][name="question-type"][value="qti"]').click()
          window.setTimeout(() => {
              document.querySelector('input[type="radio"][name="native-question-type"][value="basic"]').click()
            }
            , 250
          )
          window.setTimeout(() => {
              document.querySelector('input[type="radio"][name="qti-question-type"][value="flashcard"]').click()
            }
            , 250
          )
          break
        case ('marker'):
          document.querySelector('input[type="radio"][name="question-type"][value="qti"]').click()
          window.setTimeout(() => {
              document.querySelector('input[type="radio"][name="native-question-type"][value="sketcher"]').click()
            }
            , 250
          )
          window.setTimeout(() => {
              document.querySelector('input[type="radio"][name="qti-question-type"][value="marker"]').click()
            }
            , 250
          )
          break
      }
    },
    async moveSketcherFromTab (loadStructure) {
      if (loadStructure) {
        this.receivedStructure = false
        const iframe = document.getElementById('sketcher')
        iframe.contentWindow.postMessage('save', '*')
        await this.handleGetStructure()
      }
      const to = document.getElementById('from-sketcher-component')
      const from = document.getElementById('to-sketcher-component')
      this.$nextTick(() => {
        if (from && to) {
          while (from.firstChild) {
            to.appendChild(from.firstChild)
          }
        }
      })
    },
    moveSketcherToTab () {
      this.$nextTick(() => {
        const from = document.getElementById('from-sketcher-component')
        const to = document.getElementById('to-sketcher-component')
        if (from && to) {
          while (from.firstChild) {
            to.appendChild(from.firstChild)
          }
        }
      })
    },
    getTabClass (tab) {
      return this.tabErrors[tab] ? 'invalid-question-editor-tab-title' : 'question-editor-tab-title'
    },
    updateTabInvalids () {
      const tabs = ['properties', 'primary-content', 'secondary-content', 'accessibility-alternatives', 'private-notes', 'rubric']
      for (let i = 0; i < tabs.length; i++) {
        const tab = document.getElementById(tabs[i])
        if (tab) {
          this.tabErrors[tabs[i]] = tab.querySelector('.invalid-feedback') !== null
        }
      }
    },
    setKeyValue (key, value) {
      let camelCase
      camelCase = key.replace(/([A-Z])/g, '_$1').toLowerCase()
      if (['name', 'description'].includes(camelCase)) {
        camelCase = 'rubric_' + camelCase
      }
      if (camelCase === 'rubric_template') {
        camelCase = 'rubric_template_id'
      }
      this.getTabClass('rubric')
      this.questionForm[camelCase] = value
      console.error(this.questionForm[camelCase])
    },
    async checkForStudentSubmissions (automaticallyUpdateRevision) {
      let checkSubmissions
      checkSubmissions = true
      if (+automaticallyUpdateRevision) {
        if (this.qtiQuestionType === 'discuss_it') {
          const newMediaUploads = this.questionForm.media_uploads
          console.log(newMediaUploads)
          console.log(this.mediaUploads)
          const oldMediaUploads = JSON.parse(this.questionToEdit.qti_json).media_uploads
          console.log(oldMediaUploads)
          const hasDifferentS3Key = newMediaUploads.some(newItem => !oldMediaUploads.some(oldItem => newItem.s3_key === oldItem.s3_key)) || oldMediaUploads.some(oldItem => !newMediaUploads.some(newItem => newItem.s3_key === oldItem.s3_key))
          // will have to revisit this because I'll have to update the question_revisions
          if (!hasDifferentS3Key) {
            // checkSubmissions = false
          }
          if (checkSubmissions) {
            try {
              const { data } = await axios.get(`/api/submissions/exists-in-current-owner-course-by-question/${this.questionToEdit.id}`)
              if (data.type === 'error') {
                this.$noty.error(data.message)
              } else if (data.submissions_exist) {
                this.$bvModal.show('modal-submissions-exist-warning')
              }
            } catch (error) {
              this.$noty.error(error.message)
            }
          }
        }
      }
    },
    editDiscussItText (activeMedia) {
      this.activeQuestionMediaUpload = activeMedia
      this.activeQuestionMediaUpload.is_edit = true
      this.discussItTextForm = new Form({
        text: activeMedia.text,
        description: activeMedia.original_filename
      })
      this.$bvModal.show('modal-discuss-it-text')
    },
    initDiscussItText () {
      this.discussItTextForm = new Form({
        text: '',
        description: ''
      })
      this.$bvModal.show('modal-discuss-it-text')
    },
    async saveDiscussItText () {
      try {
        const action = this.activeQuestionMediaUpload.s3_key ? 'patch' : 'post'
        if (action === 'patch') {
          this.discussItTextForm.s3_key = this.activeQuestionMediaUpload.s3_key
        }
        const { data } = await this.discussItTextForm[action]('/api/question-media/text')
        if (data.type === 'error') {
          this.$noty.error(data.message)
        } else {
          const questionMediaUpload = {
            s3_key: data.s3_key,
            size: data.size,
            text: this.discussItTextForm.text,
            original_filename: this.discussItTextForm.description,
            order: this.questionForm.media_uploads.length + 1
          }
          switch (action) {
            case ('post'):
              if (!this.questionForm.media_uploads) {
                this.questionForm.media_uploads = []
              }
              this.questionForm.media_uploads.push(questionMediaUpload)
              break
            case ('patch'):
              for (let i = 0; i < this.questionForm.media_uploads.length; i++) {
                const mediaUpload = this.questionForm.media_uploads[i]
                if (mediaUpload.s3_key === data.s3_key) {
                  this.questionForm.media_uploads[i].text = this.discussItTextForm.text
                  this.questionForm.media_uploads[i].original_filename = this.discussItTextForm.description
                }
              }
              break
          }
          this.$forceUpdate()
          this.$bvModal.hide('modal-discuss-it-text')
        }
      } catch (error) {
        if (!error.message.includes('status code 422')) {
          this.$noty.error(error.message)
        } else {
          this.allFormErrors = this.discussItTextForm.errors.flatten()
          this.$bvModal.show('modal-form-errors-discuss-it-text-form')
        }
      }
    },
    getPromptHeader () {
      return this.qtiQuestionType === 'discuss_it'
        ? '<h2 class="h7">Instructions</h2>'
        : '<h2 class="h7">Prompt</h2>'
    },
    updateQuestionMediaUploadsOrder (orderedMediaUploads) {
      this.questionForm.media_uploads = orderedMediaUploads
    },
    receiveMessage (event) {
      console.error(event.data)
      console.error(this.nativeType)
      switch (this.nativeType) {
        case ('sketcher'):
          if (event.data.structure) {
            this.receivedStructure = true
            this.qtiJson.solutionStructure = event.data.structure
            this.solutionStructure = event.data.structure
            this.questionForm.solution_structure = JSON.stringify(this.qtiJson.solutionStructure)
            this.questionForm.qti_prompt = this.qtiJson.prompt
            this.questionForm.qti_json = JSON.stringify(this.qtiJson)
            this.$forceUpdate()
          }
          break
      }
    },
    viewInLibreStudio (id) {
      window.open(
        `https://studio.libretexts.org/h5p/${id}`,
        '_blank' // <- This is what makes it open in a new window.
      )
    },
    setCKEditorKeydownAsTrue () {
      this.ckeditorKeyDown = true
      console.log('trued')
    },
    updateQuestionTranscript (activeMedia, transcript) {
      this.questionForm.media_uploads.find(item => item.id === activeMedia.id).transcript = transcript
      console.log(activeMedia.id)
      console.log(transcript)
      this.questionMediaUploadKey++
    },
    deleteQuestionMediaUpload (activeQuestionMediaUpload) {
      this.questionForm.media_uploads = this.questionForm.media_uploads.filter(item => item.s3_key !== activeQuestionMediaUpload.s3_key)
      this.questionMediaUploadKey++
    },
    updateQuestionMediaUploads (questionMediaUpload) {
      if (this.qtiQuestionType === 'discuss_it') {
        questionMediaUpload.order = this.questionForm.media_uploads.length + 1
      }
      this.questionForm.media_uploads.push(questionMediaUpload)
      this.questionMediaUploadKey++
    },
    async showA11yAutoGradedQuestion () {
      let questionId = this.questionForm.a11y_auto_graded_question_id
      if (questionId) {
        if (questionId.toString().includes('-')) {
          questionId = questionId.split('-')[1]
        }
      }
      try {
        const { data } = await axios.get(`/api/questions/${questionId}`)
        if (data.type === 'success') {
          this.a11yAutoGradedAlternativeQuestionId = questionId
          this.$bvModal.show('modal-view-a11y-auto-graded-question')
        } else {
          this.$noty.error(data.message)
        }
      } catch (error) {
        if (!error.message.includes('status code 404')) {
          this.$noty.error(error.message)
        } else {
          this.$noty.error('That is not a valid ADAPT ID.')
        }
      }
    },
    reloadQuestionRevisionDifferences (mathJaxRendered, diffsShown) {
      this.mathJaxRendered = mathJaxRendered
      this.diffsShown = diffsShown
      this.questionRevisionDifferencesKey++
    },
    initImageSize (type) {
      switch (type) {
        case ('pixels'):
          this.resizeWidth = Math.round(this.webworkImageOptions.width / 100 * this.resizeWidth)
          this.resizeHeight = Math.round(this.webworkImageOptions.height / 100 * this.resizeHeight)
          break
        case ('percentage'):
          this.resizeWidth = Math.round(100 * this.resizeWidth / this.webworkImageOptions.width)
          this.resizeHeight = Math.round(100 * this.resizeHeight / this.webworkImageOptions.height)
          break
        default:
          alert('Not a valid type of image size.')
      }
    },
    resetImageResize () {
      switch (this.resizeImageBy) {
        case ('pixels'):
          this.resizeWidth = this.webworkImageOptions.width
          this.resizeHeight = this.webworkImageOptions.height
          break
        case ('percentage'):
          this.resizeWidth = 100
          this.resizeHeight = 100
          break
        default:
          alert('Not a valid type of image size.')
      }
      this.initResizeWidth = this.resizeWidth
      this.initResizeHeight = this.resizeHeight
    },
    updateResizeHeight () {
      if (this.maintainAspectRatio) {
        this.resizeHeight = Math.round(this.resizeHeight * this.resizeWidth / this.initResizeWidth)
      }
    },
    updateResizeWidth () {
      if (this.maintainAspectRatio) {
        this.resizeWidth = Math.round(this.resizeWidth * this.resizeHeight / this.initResizeHeight)
      }
    },
    setWebworkAttachmentOptions (webworkAttachment) {
      this.webworkImageOptions.filename = webworkAttachment.filename
      this.webworkImageOptions.width = webworkAttachment.width
      this.webworkImageOptions.height = webworkAttachment.height
      this.resizeWidth = 100
      this.resizeHeight = 100
    },
    unrenderMathJax () {
      this.mathJaxRendered = false
      this.differences = []
      this.$nextTick(() => {
        this.compareRevisions()
        this.$forceUpdate()
      })
    },
    waitForStructure () {
      return new Promise((resolve) => {
        const intervalId = setInterval(() => {
          if (this.receivedStructure) {
            clearInterval(intervalId)
            resolve()
          } else {
            console.log('Checking for receivedStructure...')
          }
        }, 50)
      })
    },
    async handleGetStructure () {
      await this.waitForStructure()
      this.message = 'Structure received!'
    },
    async initSaveQuestion () {
      if (window.self !== window.top) {
        window.parent.postMessage('scroll-to-top', '*')
      }
      if (!this.checkedOpenEndedSubmissionType &&
        this.questionForm.technology === 'text' &&
        this.questionForm.non_technology_text !== '' &&
        this.questionForm.open_ended_submission_type === '0') {
        this.checkedOpenEndedSubmissionType = true
        this.$bvModal.show('modal-confirm-no-open-ended-submission-with-no-auto-grading')
        return
      }
      this.threeDModelSolutionStructureErrors = ''
      this.questionForm.changes_are_topical = ''
      console.log(`Technology: ${this.questionForm.technology}`)
      if (!this.validateImagesHaveAlts()) {
        return false
      }
      this.questionForm.session_identifier = this.sessionIdentifier
      this.questionForm.source_url_required = true
      this.questionForm.framework_item_sync_question = this.frameworkItemSyncQuestion
      this.questionForm.webwork_attachments = this.webworkAttachments
      if (this.questionForm.source_url) {
        const withHttps = url => !/^https?:\/\//i.test(url) ? `https://${url}` : url
        this.questionForm.source_url = withHttps(this.questionForm.source_url)
      }
      if (this.originalPreexistingWebworkCode.length &&
        this.originalPreexistingWebworkCode === this.questionForm.webwork_code) {
        this.$noty.info('Please make some changes to the webWork code before saving it as your own.')
        return false
      }
      if (this.questionForm.technology === 'qti') {
        for (const key in this.questionForm) {
          if (key.includes('qti_')) {
            console.log(key)
            delete this.questionForm[key]
          }
        }

        switch (this.qtiQuestionType) {
          case ('flashcard'):
            this.$forceUpdate()
            const cardData = this.$refs.flashcard.getCardData()
            if (!cardData) return false
            this.qtiJson = {
              questionType: 'flashcard',
              card: cardData
            }
            this.questionForm.qti_json = JSON.stringify(this.qtiJson)
            break
          case ('forge'):
            this.questionForm.qti_json = JSON.stringify(this.qtiJson)
            this.questionForm.qti_prompt = this.qtiJson['prompt']
            break
          case ('accounting_report'):
            this.questionForm.qti_prompt = this.qtiJson['prompt']
            this.questionForm.qti_json = JSON.stringify(this.qtiJson)
            break
          case ('accounting_journal_entry'):
            this.questionForm.qti_json = JSON.stringify(this.qtiJson)
            break
          case ('accounting_multi_part_computation'):
            this.questionForm.qti_prompt = this.qtiJson['prompt']
            // Shuffle dropdown options once for fixed order; backend re-shuffles per student if randomizeDropdowns is true
            const mpcJson = JSON.parse(JSON.stringify(this.qtiJson)) // deep clone, never mutate the editor state
            mpcJson.tables.forEach(table => {
              table.rows.forEach(row => {
                if (row.rowType === 'data' && row.cells) {
                  row.cells.forEach(cell => {
                    if (cell.mode === 'answer' && cell.answerType === 'dropdown' && cell.dropdownOptions && cell.dropdownOptions.length > 0) {
                      // Fisher-Yates shuffle on a copy
                      const shuffled = [...cell.dropdownOptions]
                      for (let i = shuffled.length - 1; i > 0; i--) {
                        const j = Math.floor(Math.random() * (i + 1));
                        [shuffled[i], shuffled[j]] = [shuffled[j], shuffled[i]]
                      }
                      cell.shuffledOptions = shuffled
                    }
                  })
                }
              })
            })
            this.questionForm.qti_json = JSON.stringify(mpcJson)
            break
          case ('three_d_model_multiple_choice'):
            this.questionForm.qti_prompt = this.qtiJson['prompt']
            this.questionForm.qti_json = JSON.stringify(this.qtiJson)
            this.questionForm.parameters = this.qtiJson.parameters
            break
          case ('discuss_it'):
            this.$forceUpdate()
            this.questionForm.qti_prompt = this.qtiJson['prompt']
            this.questionForm.qti_json = JSON.stringify(this.qtiJson)
            break
          case ('submit_molecule'):
          case ('marker'):
            this.receivedStructure = false
            const iframe = document.getElementById('sketcher')
            iframe.contentWindow.postMessage('save', '*')
            await this.handleGetStructure()
            break
          case ('highlight_table'):
            this.$forceUpdate()
            this.questionForm.colHeaders = this.qtiJson['colHeaders']
            this.questionForm.rows = this.qtiJson.rows
            this.questionForm.qti_json = JSON.stringify(this.qtiJson)
            break
          case ('highlight_text'):
            this.$forceUpdate()
            this.questionForm.qti_prompt = this.qtiJson['prompt']
            this.questionForm.responses = this.qtiJson.responses
            this.questionForm.qti_json = JSON.stringify(this.qtiJson)
            break
          case ('drag_and_drop_cloze'):
            this.$forceUpdate()
            this.questionForm.qti_prompt = this.qtiJson['prompt']
            this.questionForm.correct_responses = this.qtiJson.correctResponses
            this.questionForm.distractors = this.qtiJson.distractors
            this.questionForm.qti_json = JSON.stringify(this.qtiJson)
            break
          case ('bow_tie'):
            this.$forceUpdate()
            this.questionForm.qti_prompt = this.qtiJson['prompt']
            this.questionForm.actions_to_take = this.qtiJson.actionsToTake
            this.questionForm.potential_conditions = this.qtiJson.potentialConditions
            this.questionForm.parameters_to_monitor = this.qtiJson.parametersToMonitor
            this.questionForm.qti_json = JSON.stringify(this.qtiJson)
            break
          case ('multiple_response_select_all_that_apply'):
            this.$forceUpdate()
            this.questionForm.qti_prompt = this.qtiJson['prompt']
            this.questionForm.responses = this.qtiJson.responses
            this.questionForm.qti_json = JSON.stringify(this.qtiJson)
            break
          case ('multiple_response_select_n'):
            this.$forceUpdate()
            this.questionForm.qti_prompt = this.qtiJson['prompt']
            this.questionForm.responses = this.qtiJson.responses
            const regex = /\[[1-9]\d*]/
            let match = this.questionForm.qti_prompt.match(regex)
            this.qtiJson.numberToSelect = match ? match[0].replace('[', '').replace(']', '') : 0
            this.questionForm.qti_json = JSON.stringify(this.qtiJson)
            break
          case ('matrix_multiple_response'):
          case ('multiple_response_grouping'):
          case ('matrix_multiple_choice'):
            this.$forceUpdate()
            this.questionForm.qti_prompt = this.qtiJson['prompt']
            if (this.qtiQuestionType === 'matrix_multiple_response') {
              this.questionForm.colHeaders = this.qtiJson.colHeaders
            } else {
              this.questionForm.headers = this.qtiJson.headers
            }
            this.questionForm.rows = this.qtiJson.rows
            if (this.qtiQuestionType === 'multiple_response_grouping') {
              for (let i = 0; i < this.questionForm.rows.length; i++) {
                let row = this.questionForm.rows[i]
                for (let j = 0; j < row.responses.length; j++) {
                  let response = row.responses[j]
                  if (!response.hasOwnProperty('correctResponse')) {
                    response.correctResponse = false
                  }
                }
              }
            }
            this.questionForm.qti_json = JSON.stringify(this.qtiJson)
            break
          case ('drop_down_table'):
            this.$forceUpdate()
            this.questionForm.qti_prompt = this.qtiJson['prompt']
            this.questionForm.colHeaders = this.qtiJson.colHeaders
            this.questionForm.rows = this.qtiJson.rows
            this.questionForm.qti_json = JSON.stringify(this.qtiJson)
            break
          case ('numerical'):
            this.$forceUpdate()
            this.questionForm.qti_prompt = this.qtiJson['prompt']
            this.questionForm.correct_response = this.qtiJson.correctResponse.value
            this.questionForm.margin_of_error = this.qtiJson.correctResponse.toleranceType === 'relative'
              ? '0'
              : this.qtiJson.correctResponse.marginOfError
            this.questionForm.relative_tolerance = this.qtiJson.correctResponse.toleranceType === 'relative'
              ? this.qtiJson.correctResponse.relativeTolerance
              : '0'
            this.questionForm.qti_json = JSON.stringify(this.qtiJson)
            break
          case ('multi_numerical'):
            this.$forceUpdate()
            this.questionForm.qti_prompt = this.qtiJson['prompt']
            if (this.qtiJson.placeholders && this.qtiJson.placeholders.length) {
              this.questionForm.placeholders = JSON.stringify(this.qtiJson.placeholders)
              const first = this.qtiJson.placeholders[0]
              this.questionForm.correct_response = first.value
              this.questionForm.margin_of_error = first.toleranceType === 'absolute' ? first.absoluteTolerance : '0'
            }
            this.questionForm.qti_json = JSON.stringify(this.qtiJson)
            break
          case ('matching'):
            this.$forceUpdate()
            this.questionForm.qti_prompt = this.qtiJson['prompt']
            this.qtiJson.termsToMatch = []
            this.qtiJson.possibleMatches = []
            let usedTermsToMatch = []
            console.log(this.termsToMatch)
            for (let i = 0; i < this.$refs.matching.termsToMatch.length; i++) {
              let item = this.$refs.matching.termsToMatch[i]
              if (!usedTermsToMatch.includes(item.termToMatch)) {
                this.questionForm[`qti_matching_term_to_match_${i}`] = item.termToMatch
                this.qtiJson.termsToMatch.push({
                  identifier: item.identifier,
                  termToMatch: item.termToMatch,
                  matchingTermIdentifier: this.$refs.matching.possibleMatches[i].identifier,
                  feedback: this.$refs.matching.termsToMatch[i].feedback
                })
                if (item.termToMatch !== '') {
                  usedTermsToMatch.push(item.termToMatch)
                }
              } else {
                this.$noty.error(`${item.termToMatch} appears multiple times as a term to match.`)
                return false
              }
            }

            let usedPossibleMatches = []
            let distractorIndex = 0
            let possibleMatchIndex = 0
            let key
            for (let i = 0; i < this.$refs.matching.possibleMatches.length; i++) {
              let item = this.$refs.matching.possibleMatches[i]
              let distractor = this.$refs.matching.matchingDistractors.find(distractor => distractor.identifier === item.identifier)
              if (!usedPossibleMatches.includes(item.matchingTerm)) {
                if (distractor) {
                  key = `qti_matching_distractor_${distractorIndex}`
                  item.matchingTerm = distractor.matchingTerm
                  distractorIndex++
                } else {
                  key = `qti_matching_matching_term_${possibleMatchIndex}`
                  possibleMatchIndex++
                }
                this.questionForm[key] = item.matchingTerm
                this.qtiJson.possibleMatches.push({
                  identifier: item.identifier,
                  matchingTerm: item.matchingTerm
                })
                if (item.matchingTerm !== '') {
                  usedPossibleMatches.push(item.matchingTerm)
                }
              } else {
                this.$noty.error(`${item.matchingTerm} appears multiple times as a potential matching term.`)
                return false
              }
            }

            this.questionForm.qti_json = JSON.stringify(this.qtiJson)
            break
          case ('multiple_answers'):
          case ('multiple_choice'):
          case ('true_false'):
            for (const property in this.questionForm) {
              if (property.startsWith('qti_simple_choice_')) {
                // clean up in case it's been deleted then recreate from the json below
                delete this.questionForm[property]
              }
            }

            this.questionForm.qti_prompt = this.qtiJson['prompt']
            if (['select_choice', 'multiple_choice'].includes(this.qtiQuestionType)) {
              this.questionForm.qti_randomize_order = this.qtiJson.randomizeOrder
            }
            let correctResponse = this.qtiJson.simpleChoice.find(choice => choice.correctResponse)
            if (!correctResponse) {
              this.$noty.error('Please choose at least one correct response before submitting.')
              return false
            }
            // delete this.qtiJson.feedbackEditorShown
            for (let i = 0; i < this.qtiJson.simpleChoice.length; i++) {
              // delete this.qtiJson.simpleChoice[i].editorShown
              this.questionForm[`qti_simple_choice_${i}`] = this.qtiJson.simpleChoice[i].value
            }
            switch (this.questionType) {
              case ('true_false'):
                this.qtiJson['language'] = this.trueFalseLanguage
                this.qtiJson['questionType'] = 'true_false'
                break
              case ('multiple_choice'):
                this.qtiJson['questionType'] = 'multiple_choice'
                break
              case ('multiple_answers'):
                this.qtiJson['questionType'] = 'multiple_answers'
                break
            }

            this.questionForm.qti_json = JSON.stringify(this.qtiJson)
            break
          case ('fill_in_the_blank'):
            this.questionForm.qti_item_body = this.qtiJson.itemBody
            this.questionForm.qti_text_entry_interactions = this.textEntryInteractions
            this.questionForm.uTags = this.$refs.fillInTheBlank.uTags
            this.qti_json = textEntryInteractionJson
            let qtiJson = {}
            qtiJson.responseDeclaration = {}
            qtiJson.responseDeclaration.correctResponse = this.$refs.fillInTheBlank.getFillInTheBlankResponseDeclarations()
            console.log(qtiJson)
            let textEntryInteraction = JSON.parse(JSON.stringify(this.qtiJson.itemBody.textEntryInteraction))
            console.log(textEntryInteraction)
            let textInteractionWithoutUnderlines = textEntryInteraction.replace(/\<u>(.*?)<\/u>/gm, function () {
              return '<u></u>'
            })
            qtiJson.itemBody = { textEntryInteraction: textInteractionWithoutUnderlines }
            qtiJson['questionType'] = 'fill_in_the_blank'
            this.questionForm.qti_json = JSON.stringify(qtiJson)
            break
          case ('drop_down_rationale_dyad'):
          case ('drop_down_rationale_triad'):
          case ('select_choice'):
            this.$forceUpdate()
            if (this.qtiJson.questionType === 'drop_down_rationale_dyad') {
              this.qtiJson.dropDownRationaleType = 'dyad'
              this.qtiJson.questionType = 'drop_down_rationale'
            }
            for (const selectChoice in this.qtiJson.inline_choice_interactions) {
              this.questionForm[`qti_select_choice_${selectChoice}`] = this.qtiJson.inline_choice_interactions[selectChoice]
            }
            this.questionForm['qti_item_body'] = this.qtiJson.itemBody
            this.questionForm.qti_json = JSON.stringify(this.qtiJson)
            break
        }
      } else {
        this.questionForm.qti_json = null
      }
      const { data } = await axios.get('/api/questions/non-meta-properties')
      const nonMetaProperties = data.non_meta_properties
      if (this.isEdit) {
        if (this.powerUser) {
          this.revisionAction = ''
        } else {
          this.revisionAction = 'propagate'
          for (let i = 0; i < nonMetaProperties.length; i++) {
            const nonMetaProperty = nonMetaProperties[i]
            if (this.questionForm[nonMetaProperty] !== this.questionToEdit[nonMetaProperty]) {
              console.log(nonMetaProperty + ': ' + this.questionForm[nonMetaProperty] + ' --- ' + this.questionToEdit[nonMetaProperty])
              this.revisionAction = 'notify'
            }
          }
        }
        if (window.self !== window.top) {
          window.parent.postMessage('scroll-to-top', '*')
        }
        this.$bvModal.show('modal-reason-for-edit')
      } else {
        this.revisionAction = 'none'
        await this.saveQuestion()
      }
    },
    initCompareRevisions () {
      this.revision1Id = this.revision2Id = this.revisionOptions[0].value
      this.$bvModal.show('modal-compare-revisions')
    },
    increaseModalSize () {
      this.$nextTick(() => {
        document.getElementById('modal-compare-revisions').getElementsByClassName('modal-dialog')[0].style.maxWidth = '90%'
      })
    },
    compareRevisions () {
      this.revision1 = this.revisionOptions.find(revision => revision.value === this.revision1Id)
      this.revision2 = this.revisionOptions.find(revision => revision.value === this.revision2Id)
      this.questionRevisionDifferencesKey++
    },
    submitSaveAndPropagate () {
      if (!this.questionForm.changes_are_topical) {
        this.$noty.info('Please check the box stating that you agree that the changes are topical in nature.')
        return false
      }
      this.$bvModal.hide('modal-reason-for-edit')
      this.saveQuestion()
    },
    setNewQuestionToEdit (revision) {
      this.$emit('setQuestionRevision', revision)
    },
    async setQuestionToEdit () {
      this.isLoadingEdit = true
      await this.getCurrentQuestionEditor()
      await this.updateCurrentQuestionEditor()
      this.checkForOtherQuestionEditors()
      this.isEdit = true
      this.powerUser = this.isAdmin
      console.log(this.questionToEdit)
      this.isWebworkDownloadOnly = !await this.canEdit(this.isAdmin, this.user, this.questionToEdit) && this.questionToEdit.technology === 'webwork'
      if (!this.isWebworkDownloadOnly) {
        await this.getRevisions(this.questionToEdit)
        if (this.questionToEdit.technology === 'webwork' && this.questionToEdit.webwork_code) {
          await this.getWebworkAttachments()
        }
      }
      this.questionForm.folder_id = this.questionToEdit.folder_id
      this.showFolderOptions = this.user.id === this.questionToEdit.question_editor_user_id
      if (this.isAdmin) {
        this.showFolderOptions = true
        await this.getAllQuestionEditors()
        await this.getAdminFolderOptions(this.questionToEdit.question_editor_user_id)
      }
      this.initiallyWebworkQuestion = this.questionToEdit.technology === 'webwork'
      await this.getFrameworkItemSyncQuestion()
      if (this.questionToEdit.attachments) {
        this.questionToEdit.attachments = JSON.parse(this.questionToEdit.attachments)
      }
      if (this.questionToEdit.rubric) {
        this.isEditRubric = true
        this.questionToEdit.rubric_shown = JSON.parse(this.questionToEdit.rubric).rubric_shown
      }
      if (this.questionToEdit.learning_outcomes) {
        this.subject = this.questionToEdit.subject
        await this.getLearningOutcomes(this.subject)
      }
      if (this.questionToEdit.qti_json) {
        this.qtiJson = JSON.parse(this.questionToEdit.qti_json)
        if (this.qtiJson.questionType === 'discuss_it') {
          this.nativeType = 'discuss_it'
        } else if (this.nursingQuestions.includes(this.qtiJson.questionType)) {
          this.nativeType = this.nursingQuestions.includes(this.qtiJson.questionType) ? 'nursing' : 'basic'
        }
        if (this.qtiJson.dropDownCloze) {
          // made select_choice do double duty
          this.nativeType = 'nursing'
        }
        console.log(this.qtiJson)
        switch (this.qtiJson.questionType) {
          case ('flashcard'):
            this.qtiQuestionType = 'flashcard'
            break
          case ('forge'):
            this.qtiPrompt = this.qtiJson['prompt']
            this.qtiQuestionType = 'forge'
            this.nativeType = 'forge'
            this.newAutoGradedTechnology = 'forge'
            break
          case ('accounting_journal_entry'):
            this.qtiQuestionType = 'accounting_journal_entry'
            this.nativeType = 'accounting'
            break
          case ('accounting_report'):
            this.qtiQuestionType = 'accounting_report'
            this.nativeType = 'accounting'
            break
          case ('accounting_multi_part_computation'):
            this.qtiQuestionType = 'accounting_multi_part_computation'
            this.nativeType = 'accounting'
            break
          case ('discuss_it'):
            this.qtiPrompt = this.qtiJson['prompt']
            this.qtiQuestionType = this.qtiJson.questionType
            break
          case ('three_d_model_multiple_choice'):
            this.qtiQuestionType = this.qtiJson.questionType
            this.nativeType = '3d_model'
            if (!this.qtiJson.feedback) {
              this.qtiJson.feedback = {}
            }
            break
          case ('submit_molecule'):
          case ('marker'):
            this.qtiQuestionType = this.qtiJson.questionType
            this.qtiPrompt = this.qtiJson['prompt']
            this.solutionStructure = this.qtiJson.solutionStructure
            this.nativeType = 'sketcher'
            if (this.qtiJson.questionType === 'marker') {
              this.sketcherType = 'marker-only'
            }
            break
          case ('drag_and_drop_cloze'):
            this.qtiQuestionType = this.qtiJson.questionType
            this.qtiPrompt = this.qtiJson['prompt']
            let correctResponses = []
            for (let i = 0; i < this.qtiJson.correctResponses.length; i++) {
              correctResponses.push(this.qtiJson.correctResponses[i].value)
            }
            let allSelects = String(this.qtiJson.prompt).split(/(\[.*?])/)
            let j = 0
            for (let i = 0; i < allSelects.length; i++) {
              if (allSelects[i] === '[select]') {
                allSelects[i] = '[' + correctResponses[j] + ']'
                j++
              }
            }
            this.qtiJson.prompt = allSelects.join('')
            break
          case ('highlight_text'):
          case ('highlight_table'):
          case ('matrix_multiple_choice'):
          case ('drop_down_rationale_dyad'):
          case ('drop_down_rationale_triad'):
          case ('drop_down_table'):
          case ('multiple_response_grouping'):
          case ('multiple_response_select_n'):
          case ('multiple_response_select_all_that_apply'):
          case ('bow_tie'):
            this.qtiQuestionType = this.qtiJson.questionType
            break
          case ('numerical'):
            this.qtiQuestionType = 'numerical'
            this.qtiPrompt = this.qtiJson['prompt']
            if (!this.qtiJson.feedback) {
              this.qtiJson.feedback = {}
            }
            // Backwards compat: set toleranceType if missing
            if (!this.qtiJson.correctResponse.toleranceType) {
              this.$set(this.qtiJson.correctResponse, 'toleranceType', 'absolute')
            }
            if (!this.qtiJson.correctResponse.relativeTolerance) {
              this.$set(this.qtiJson.correctResponse, 'relativeTolerance', '0')
            }
            break
          case ('multi_numerical'):
            this.qtiQuestionType = 'multi_numerical'
            this.qtiPrompt = this.qtiJson['prompt']
            if (!this.qtiJson.placeholders || !this.qtiJson.placeholders.length) {
              this.$set(this.qtiJson, 'placeholders', [
                {
                  value: this.qtiJson.correctResponse?.value ?? '',
                  toleranceType: 'absolute',
                  absoluteTolerance: this.qtiJson.correctResponse?.marginOfError ?? '0',
                  relativeTolerance: '0'
                }
              ])
            }
            if (!this.qtiJson.feedback) {
              this.qtiJson.feedback = {}
            }
            break
          case ('matching'):
            this.qtiQuestionType = 'matching'
            this.qtiPrompt = this.qtiJson['prompt']
            break
          case ('true_false'):
          case ('multiple_choice'):
            let qtiQuestionType = this.qtiQuestionType
            this.qtiQuestionType = ''
            this.qtiPrompt = this.qtiJson['prompt']
            this.simpleChoices = this.qtiJson.simpleChoice
            this.qtiJson.feedbackEditorShown = {}

            if (typeof this.qtiJson.feedback === 'undefined' || JSON.stringify(this.qtiJson.feedback) === '[]') {
              this.qtiJson.feedback = {}
            }

            for (let i = 0; i < this.qtiJson.simpleChoice.length; i++) {
              this.qtiJson.simpleChoice[i].editorShown = false
              this.qtiJson.feedbackEditorShown[this.simpleChoices[i].identifier] = false
              if (!this.qtiJson.feedback[this.simpleChoices[i].identifier]) {
                this.qtiJson.feedback[this.simpleChoices[i].identifier] = ''
              }
            }
            this.qtiQuestionType = qtiQuestionType
            break
          case ('matrix_multiple_response'):
            this.qtiQuestionType = 'matrix_multiple_response'
            this.qtiPrompt = this.qtiJson['prompt']
            break
          case ('multiple_answers'):
            this.qtiQuestionType = 'multiple_answers'
            this.qtiPrompt = this.qtiJson['prompt']
            break
          case ('fill_in_the_blank'):
            this.qtiQuestionType = 'fill_in_the_blank'
            break
          case ('drop_down_rationale'):
            this.qtiQuestionType = `drop_down_rationale_${this.qtiJson.dropDownRationaleType}`
            this.qtiJson.questionType = this.qtiQuestionType
            break
          case ('select_choice'):
            this.qtiQuestionType = 'select_choice'
            break
          default:
            alert('Not a valid question type:' + this.qtiJson.questionType)
        }
      }
      if (this.nursingQuestions.includes(this.qtiJson.questionType)) {
        if (!this.qtiJson.feedback) {
          this.qtiJson.feedback = { correct: '', incorrect: '' }
        }
      }
      for (let i = 0; i < this.editorGroups.length; i++) {
        let editorGroup = this.editorGroups[i]
        switch (editorGroup.id) {
          case ('qti'):
            editorGroup.expanded = this.qtiPrompt
            break
          case ('technology'):
            editorGroup.expanded = this.questionToEdit.technology !== 'text'
            break
          case ('a11y_auto_graded_question_id'):
            editorGroup.expanded = this.questionToEdit.a11y_auto_graded_question_id
            break
          default:
            editorGroup.expanded = this.questionToEdit[editorGroup.id]
        }
      }
      switch (this.questionToEdit.technology) {
        case ('webwork'):
          if (this.questionToEdit.webwork_code) {
            this.newAutoGradedTechnology = 'webwork'
          } else {
            this.existingQuestionFormTechnology = 'webwork'
          }
          break
        case ('imathas'):
        case ('h5p'):
          this.existingQuestionFormTechnology = this.questionToEdit.technology
          break
        case ('qti'):
          if (this.qtiJson.questionType !== 'forge') {
            this.newAutoGradedTechnology = 'qti'
          }
          break
      }

      if (this.questionToEdit.webwork_code) {
        this.newAutoGradedTechnology = 'webwork'
        this.webworkEditorShown = true
        this.questionToEdit.new_auto_graded_code = 'webwork'
      }
      if (this.questionToEdit.license_version) {
        this.questionToEdit.license_version = Number(this.questionToEdit.license_version).toFixed(1) // some may be saved as 4 vs 4.0 in the database
      }
      if (this.questionToEdit.question_subject_id) {
        await this.getQuestionChapterIdOptions(this.questionToEdit.question_subject_id)
      }
      if (this.questionToEdit.question_chapter_id) {
        await this.getQuestionSectionIdOptions(this.questionToEdit.question_chapter_id)
      }
      this.questionForm = new Form(this.questionToEdit)

      this.questionFormTechnology = this.questionForm.technology
      console.log(this.questionForm)
      console.log(this.questionToEdit)
      this.updateLicenseVersions(this.questionForm.license)
      if (this.questionToEdit.tags.length === 1 && this.questionToEdit.tags[0] === 'none') {
        this.questionForm.tags = []
      }

      if (this.isWebworkDownloadOnly) {
        this.disableTabs()
      }
      setTimeout(() => {
        this.isLoadingEdit = false
      }, 2000)
    },
    disableTabs () {
      this.$nextTick(() => {
        document.querySelectorAll(
          '#question-editor-tabs__BV_tab_container_ button,#question-editor-tabs__BV_tab_container_ select,#question-editor-tabs__BV_tab_container_ textarea,#question-editor-tabs__BV_tab_container_ input,#question-editor-tabs__BV_tab_container_ radio').forEach(el => {
          el.disabled = true
        })
        document.querySelectorAll(' #primary-content button').forEach(el => {
          if (el.textContent.trim() !== 'Consult Insight') {
            el.disabled = true
          }
        })
        document.querySelector('input[type="file"].custom-file-input').disabled = true
        document.getElementById('question-media-upload-html-block').style.display = 'none'
        document.getElementById('preview-question').style.display = 'none'
        document.getElementById('save-question').style.display = 'none'
        for (const i in CKEDITOR.instances) {
          /* this returns the names of the textareas/id of the instances. */
          CKEDITOR.instances[i].setReadOnly(true)
        }
      })
    },
    async getRevisions (questionToEdit) {
      try {
        const { data } = await axios.get(`/api/question-revisions/question/${questionToEdit.id}`)
        if (data.type === 'error') {
          this.$noty.error(data.message)
          return false
        }
        this.revisionOptions = []
        if (questionToEdit.id) {
          for (let i = 0; i < data.revisions.length; i++) {
            let revision = data.revisions[i]
            revision.value = revision.id
            this.revisionOptions.push(revision)
          }
          if (this.revisionOptions.length) {
            this.revision = questionToEdit.question_revision_id ? questionToEdit.question_revision_id : this.revisionOptions[0].value
          }
        }
        console.log('got the revisions')
        console.log(this.revisionOptions)
        console.log(this.revision)
      } catch (error) {
        this.$noty.error(error.message)
      }
    },
    async getWebworkTemplateOptions () {
      try {
        const { data } = await axios.get('/api/webwork/templates')
        if (data.type === 'error') {
          this.$noty.error(data.message)
          return false
        }
        for (let i = 0; i < data.webwork_templates.length; i++) {
          let webworkTemplate = data.webwork_templates[i]
          this.webworkTemplateOptions.push({
            text: webworkTemplate.title,
            value: webworkTemplate.id,
            template: webworkTemplate.webwork_code
          })
        }
        this.webworkTemplateOptions.push({ text: 'Pre-existing problem', value: 'pre-existing problem' })
        this.webworkTemplateOptions.sort(this.compare)
        this.webworkTemplateOptions.unshift({
          text: 'Choose a template',
          value: null,
          template: ''
        })
      } catch (error) {
        this.$noty.error(error.message)
      }
    },
    compare (a, b) {
      if (a.text < b.text) {
        return -1
      }
      if (a.text > b.text) {
        return 1
      }
      return 0
    },
    updateQuestionFormRubricCategories (rubricCategories) {
      this.questionForm.rubric_categories = rubricCategories
      let totalPercent
      totalPercent = 0
      for (let i = 0; i < this.questionForm.rubric_categories.length; i++) {
        totalPercent += +this.questionForm.rubric_categories[i].percent
      }
      if (totalPercent === 100) {
        this.questionForm.errors.clear('rubric_categories')
      }
    },
    async getGradingStyles () {
      try {
        const { data } = await axios.get('/api/grading-styles')
        if (data.type !== 'success') {
          this.$noty.error(data.message)
          return false
        }
        this.gradingStyleOptions = [{ text: 'Choose a grading style', value: null }]
        for (let i = 0; i < data.grading_styles.length; i++) {
          let gradingStyle = data.grading_styles[i]
          this.gradingStyleOptions.push({ text: gradingStyle.description, value: gradingStyle.id })
        }
      } catch (error) {
        this.$noty.error(error.message)
      }
    },
    initNativeType (selectedType) {
      const type = selectedType || this.nativeType
      if (this.isLoadingEdit) {
        return
      }
      if (type === 'forge') {
        this.initQTIQuestionType('forge')
      } else if (type === 'discuss_it') {
        this.qtiQuestionType = 'discuss_it'
        this.initQTIQuestionType('discuss_it')
      } else if (type === '3d_model') {
        // CHANGED: auto-select multiple choice, only one option implemented
        this.qtiQuestionType = 'three_d_model_multiple_choice'
        this.initQTIQuestionType('three_d_model_multiple_choice')
      } else if (['nursing', 'sketcher', 'accounting'].includes(type)) {
        this.nativeType = type
        this.initNonBasicQTIQuestion()
      } else {
        this.qtiQuestionType = 'multiple_choice'
        this.initQTIQuestionType('multiple_choice')
      }
    },
    async deleteWebworkAttachment () {
      try {
        const { data } = await axios.post('/api/webwork-attachments/destroy', {
          question_id: this.questionToEdit ? this.questionToEdit.id : 0,
          webwork_attachment: this.webworkAttachmentToDelete,
          question_revision_id: this.revision
        })
        this.$noty[data.type](data.message)
        if (data.type !== 'error') {
          this.webworkAttachments = this.webworkAttachments.filter(attachment => attachment.filename !== this.webworkAttachmentToDelete.filename)
          this.$forceUpdate()
        }
      } catch (error) {
        this.$noty.error(error.message)
      }
      this.$bvModal.hide('modal-confirm-delete-webwork-attachment')
    },
    confirmDeleteWebworkAttachment (webworkAttachment) {
      this.webworkAttachmentToDelete = webworkAttachment
      this.$bvModal.show('modal-confirm-delete-webwork-attachment')
    },
    async getWebworkAttachments () {
      try {
        const { data } = await axios.get(`/api/webwork-attachments/question/${this.questionToEdit.id}/${this.revision}`)
        if (data.type === 'error') {
          this.$noty.error(data.message)
          return false
        }
        this.webworkAttachments = data.webwork_attachments
      } catch (error) {
        this.$noty.error(error.message)
      }
    },
    async copyWebworkImageCode () {
      let optionsArray = [`"${this.webworkImageOptions.filename}"`]
      if (this.resizeImageBy === 'percentage') {
        this.initImageSize('pixels')
      }
      if (this.resizeWidth) {
        optionsArray.push(`width => ${this.resizeWidth}`)
      }
      if (this.resizeHeight) {
        optionsArray.push(`height => ${this.resizeHeight}`)
      }
      if (this.webworkImageOptions.tex_size) {
        optionsArray.push(`tex_size => ${this.webworkImageOptions.tex_size}`)
      }

      if (this.webworkImageOptions.alt_text) {
        let sanitizedAltText = this.webworkImageOptions.alt_text.replaceAll('"', '``')
        optionsArray.push(`extra_html_tags => 'alt="${sanitizedAltText}"'`)
      }
      let options = optionsArray.join(', ')

      const content = this.questionForm.webwork_code && this.questionForm.webwork_code.search('BEGIN_PGML') > 0 ? `[@ image( ${options} ) @]*` : `\\{ image( ${options} ) \\}`
      try {
        await navigator.clipboard.writeText(content)
        this.$noty.success('Successfully copied!')
      } catch (err) {
        this.$noty.error(`The code could not be copied.`)
      }
      this.$bvModal.hide('modal-webwork-image-options')
    },
    onWebworkFileChange (e) {
      const file = e.target.files[0]
      if (!file) return
      this.webworkUploadFile = file
      this.webworkUploadFilename = file.name
      this.webworkUploadProgress = 0
      this.errorMessages = []
    },

    async uploadWebworkAttachmentViaPresignedUrl () {
      if (!this.webworkUploadFile) return
      this.webworkUploading = true
      this.errorMessages = []
      try {
        const { data } = await axios.post('/api/s3/pre-signed-url', {
          upload_file_type: 'webwork-attachment',
          file_name: this.webworkUploadFile.name
        })
        if (data.type === 'error') {
          this.errorMessages = [data.message]
          return
        }

        // Read width/height client-side
        const { width, height } = await this.getImageDimensions(this.webworkUploadFile)

        // Upload to S3
        await this.uploadWebworkFileWithProgress(this.webworkUploadFile, data.preSignedURL, data.s3_key)

        const attachment = {
          filename: this.webworkUploadFile.name,
          s3_key: data.s3_key,
          width,
          height,
          status: 'pending'
        }

        if (!this.webworkAttachments.find(a => a.filename === attachment.filename)) {
          this.webworkAttachments.push(attachment)
        } else {
          const idx = this.webworkAttachments.findIndex(a => a.filename === attachment.filename)
          this.webworkAttachments.splice(idx, 1, attachment)
          this.$noty.info(`${attachment.filename} has been overwritten with the newer version.`)
        }

        this.webworkUploadFilename = ''
        this.webworkUploadFile = null
        this.$refs.webworkFileInput.value = ''
      } catch (error) {
        this.errorMessages = [error.message]
      } finally {
        this.webworkUploading = false
        this.webworkUploadProgress = 0
      }
    },

    getImageDimensions (file) {
      return new Promise((resolve) => {
        const url = URL.createObjectURL(file)
        const img = new Image()
        img.onload = () => {
          resolve({ width: img.naturalWidth, height: img.naturalHeight })
          URL.revokeObjectURL(url)
        }
        img.onerror = () => {
          resolve({ width: 0, height: 0 })
          URL.revokeObjectURL(url)
        }
        img.src = url
      })
    },

    async uploadWebworkFileWithProgress (file, url, s3Key) {
      return new Promise((resolve, reject) => {
        const xhr = new XMLHttpRequest()
        xhr.upload.addEventListener('progress', (e) => {
          if (e.lengthComputable) {
            this.webworkUploadProgress = Math.round((e.loaded / e.total) * 100)
          }
        })
        xhr.onload = () => {
          if (xhr.status === 200) {
            resolve()
          } else {
            reject(new Error('Upload failed with status ' + xhr.status))
          }
        }
        xhr.onerror = () => reject(new Error('XHR network error'))
        xhr.open('PUT', url)
        xhr.send(file)
      })
    },
    async getFrameworkItemSyncQuestion () {
      try {
        const { data } = await axios.get(`/api/framework-item-sync-question/question/${this.questionToEdit.id}`)
        if (data.type === 'error') {
          this.$noty.error(data.message)
        }
        this.frameworkItemSyncQuestion = data.framework_item_sync_question
      } catch (error) {
        this.$noty.error(error.message)
      }
    },
    removeFrameworkItemSyncQuestion (itemType, itemId) {
      this.frameworkItemSyncQuestion[itemType] = this.frameworkItemSyncQuestion[itemType].filter(item => item.id !== itemId)
    },
    setFrameworkItemSyncQuestion (frameworkItemSyncQuestion) {
      this.frameworkItemSyncQuestion = frameworkItemSyncQuestion
    },
    getTextFromTechnology (technology) {
      let option = this.existingAutoGradedTechnologyOptions.find(item => item.value === technology)
      return option ? option.text : 'unknown technology'
    },
    initNonBasicQTIQuestion () {
      let questionType
      switch (this.nativeType) {
        case ('nursing'):
          questionType = 'bow_tie'
          break
        case ('sketcher'):
          questionType = 'submit_molecule'
          break
        case ('accounting'):
          questionType = 'accounting_journal_entry'
          break
      }
      this.qtiQuestionType = questionType
      this.initQTIQuestionType(questionType)
      this.questionFormTechnology = 'qti'
      this.newAutoGradedTechnology = 'qti'
      this.questionForm.technology = 'qti'
      this.editorGroups.find(editorGroup => editorGroup.id === 'technology').expanded = true
    },
    hotKeys (event) {
      if (event.key === 'Escape' && $('#modal-framework-aligner___BV_modal_content_').length) {
        this.$bvModal.hide('modal-framework-aligner')
        return
      }
      if (event.key === 'Escape' &&
        this.questionToEdit.id &&
        !$('#my-questions-question-to-view-questions-editor___BV_modal_content_').length) {
        // hack....just close if the preview isn't open.  For some reason, I couldn't get the edit modal to close
        this.$bvModal.hide(`modal-edit-question-${this.questionToEdit.id}`)
        return
      }
      if (event.ctrlKey) {
        switch (event.key) {
          case ('S'):
            this.checkedOpenEndedSubmissionType = false
            this.initSaveQuestion()
            break
          case ('V'):
            this.previewQuestion()
            break
        }
      }
    },
    async getQtiAnswerJson () {
      this.previewingQuestion = false
      try {
        const { data } = await axios.post('/api/questions/qti-answer-json', { qti_json: JSON.stringify(this.qtiJson) })
        if (data.type !== 'success') {
          this.$noty.error(data.message)
          return false
        }
        console.log(data)
        this.qtiAnswerJson = data.qti_answer_json
        this.showQtiAnswer = true
        try {
          if (this.qtiJson.questionType === 'submit_molecule') {
            this.previewingQuestion = false
            this.qtiAnswerJson = JSON.stringify(this.qtiJson)
          }
        } catch (error) {
          console.error(error.message)
          console.error('This logic is just for submit molecule.  Bt it didn;t work')
        }
        this.qtiJsonQuestionViewerKey++
      } catch (error) {
        this.$noty.error(error.message)
      }
    },
    checkForOtherQuestionEditors: function () {
      this.updateCurrentQuestionEditor()
      window.currentQuestionEditorUpdatedAt = setInterval(() => {
        if (!this.currentQuestionEditor) {
          this.getCurrentQuestionEditor()
        }
      }, 5000)
    },
    async updateCurrentQuestionEditor () {
      try {
        const { data } = await axios.patch(`/api/current-question-editor/${this.questionToEdit.id}`)
        if (data.type === 'error') {
          this.noty.error(data.message)
          return false
        }
      } catch (error) {
        this.$noty.error(error.message)
      }
    },
    async getCurrentQuestionEditor () {
      try {
        const { data } = await axios.get(`/api/current-question-editor/${this.questionToEdit.id}`)
        if (data.type === 'error') {
          this.noty.error(data.message)
          return false
        }
        this.currentQuestionEditor = data.current_question_editor
        console.error(this.currentQuestionEditor)
        if (this.currentQuestionEditor) {
          this.$bvModal.show('modal-current-question-editor')
        }
      } catch (error) {
        this.$noty.error(error.message)
      }
    },
    updateLearningOutcomes (subject) {
      let chosenLearningOutcomes = []
      for (let i = 0; i < this.questionForm.learning_outcomes.length; i++) {
        let chosenLearningOutcome = this.learningOutcomeOptions.find(learningOutcome => learningOutcome.id === this.questionForm.learning_outcomes[i])
        chosenLearningOutcomes.push(chosenLearningOutcome)
      }
      console.log(this.questionForm.learning_outcomes)
      console.log(chosenLearningOutcomes)
      this.learningOutcomeOptions = []
      this.getLearningOutcomes(subject)
      for (let i = 0; i < chosenLearningOutcomes.length; i++) {
        let chosenLearningOutcome = chosenLearningOutcomes[i]
        if (chosenLearningOutcome &&
          !this.learningOutcomeOptions.find(learningOutcomeOption => learningOutcomeOption.label === chosenLearningOutcome.label)) {
          this.learningOutcomeOptions.push(chosenLearningOutcome)
        }
      }
    },
    getLearningOutcomeLabel (chosenLearningOutcomeId) {
      if (this.learningOutcomeOptions.length) {
        let chosenLearningOutcome = this.learningOutcomeOptions.find(learningOutcome => learningOutcome.id === chosenLearningOutcomeId)
        if (chosenLearningOutcome) {
          return chosenLearningOutcome.label
        }
      }
    },
    async getDefaultSubject () {
      try {
        const { data } = await axios.get('/api/learning-outcomes/default-subject')
        if (data.type === 'error') {
          this.$noty.error(data.message)
          return false
        }
        this.subject = data.default_subject
      } catch (error) {
        this.$noty.error(error.message)
      }
    },
    async updateTemplateWithPreexistingWebworkFilePath (filePath) {
      this.updatingTempalteWithPreexistingWebworkFilePath = true
      let parts = filePath.split('-')
      let questionId = parts.pop()
      try {
        const { data } = await axios.post('/api/questions/get-webwork-code-from-file-path', { file_path: filePath })
        if (data.type === 'error') {
          this.$noty.error(data.message)
          this.updatingTempalteWithPreexistingWebworkFilePath = false
          return false
        }
        this.questionForm.webwork_code = data.webwork_code
        this.originalPreexistingWebworkCode = data.webwork_code
      } catch (error) {
        this.$noty.error(error.message)
      }
      this.$forceUpdate()
      this.updatingTempalteWithPreexistingWebworkFilePath = false
    },
    setWebworkTemplate (chosenOption) {
      this.showPreexistingWebworkFilePath = chosenOption === 'pre-existing problem'
      this.questionForm.webwork_code = this.webworkTemplateOptions.find(option => option.value === chosenOption).template
      this.$forceUpdate()
    },
    toggleGeneralFeedbackEditorShown (key, boolean) {
      this.generalFeedbacks.find(generalFeedback => generalFeedback.key === key).editorShown = boolean
      this.$forceUpdate()
    },
    initChangeExistingAutoGradedTechnology (technology) {
      this.questionForm.webwork_code = ''
      this.newAutoGradedTechnology = null
      this.showPreexistingWebworkFilePath = false
      this.preexisitingWebworkFilePath = ''
      this.webworkTemplate = null
      this.webworkEditorShown = false
      if (technology === 'text') {
        this.questionForm.a11y_auto_graded_question_id = null
      }
      if (technology === 'qti') {
        if (this.questionForm.non_technology_text) {
          this.$noty.info('Please remove any Open-Ended Content before changing to Native.  You can always move your Open-Ended Content into the Prompt of your Native question.')
          this.questionFormTechnology = this.questionForm.technology
        } else {
          this.editorGroups.find(editorGroup => editorGroup.id === 'non_technology_text').expanded = false
          this.questionForm.technology = 'qti'
          this.qtiQuestionType = 'multiple_choice'
          this.initQTIQuestionType(this.qtiQuestionType)
        }
      } else {
        this.questionForm.technology = technology
      }
    },
    initQTIQuestionType (questionType) {
      if (this.isLoadingEdit) {
        return
      }
      this.questionForm.errors.clear()
      this.qtiJson = {}
      this.simpleChoices = []
      for (let i = 0; i < this.generalFeedbacks.length; i++) {
        this.generalFeedbacks[i].editorShown = false
      }
      switch (questionType) {
        case ('flashcard'):
          this.qtiJson = { questionType: 'flashcard', card: null }
          this.qtiQuestionType = 'flashcard'
          break
        case ('forge'):
          this.qtiJson = {
            questionType: 'forge',
            prompt: this.qtiPrompt ? this.qtiPrompt : ''
          }
          this.qtiQuestionType = 'forge'
          this.qtiPrompt = ''
          break
        case ('discuss_it'):
          this.questionForm.open_ended_component = '1'
          this.qtiJson = {
            questionType: 'discuss_it',
            prompt: ''
          }
          this.qtiQuestionType = 'discuss_it'
          break
        case ('accounting_journal_entry'):
          this.qtiJson = {
            questionType: 'accounting_journal_entry',
            entries: []
          }
          break
        case ('accounting_report'):
          this.qtiJson = {
            questionType: 'accounting_report',
            prompt: '',
            reportHeading: ['', '', ''],
            orderMode: 'exact',
            columns: [
              {
                identifier: uuidv4(),
                header: '',
                type: 'text',
                textInputMode: 'text',
                dropdownOptions: []
              },
              {
                identifier: uuidv4(),
                header: '',
                type: 'numeric',
                textInputMode: 'text',
                dropdownOptions: []
              }
            ],
            rows: []
          }
          break
        case ('accounting_multi_part_computation'):
          this.qtiJson = {
            questionType: 'accounting_multi_part_computation',
            prompt: '',
            tables: []
          }
          break
        case ('three_d_model_multiple_choice'):
          this.parameters3DModel.mode = 'selection'
          this.qtiJson = {
            questionType: 'three_d_model_multiple_choice',
            parameters: this.parameters3DModel,
            generalIncorrectFeedback: '',
            feedbacks: {}
          }
          break
        case ('three_d_model_multiple_answer'):
          this.parameters3DModel.mode = 'selection'
          this.qtiJson = {
            questionType: 'three_d_model_multiple_answer',
            parameters: this.parameters3DModel,
            feedback: {}
          }
          break
        case ('submit_molecule'):
          this.qtiJson = {
            questionType: 'submit_molecule',
            prompt: '',
            solutionStructure: '',
            solution: '',
            matchStereo: '0'
          }
          this.qtiQuestionType = 'submit_molecule'
          this.$nextTick(() => {
            this.qtiQuestionType = 'submit_molecule'
          })
          break
        case ('marker'):
          this.qtiJson = {
            questionType: 'marker',
            prompt: '',
            solutionStructure: '',
            solution: '',
            partialCredit: 'exclusive',
            oneHundredPercentOverride: null
          }
          this.$nextTick(() => {
            this.qtiQuestionType = 'marker'
          })

          break
        case ('highlight_table'):
          this.qtiJson = {
            questionType: 'highlight_table',
            prompt: '',
            colHeaders: ['', ''],
            rows: [{
              header: '',
              prompt: '',
              responses: []
            }]
          }
          break
        case ('highlight_text'):
          this.qtiJson = {
            questionType: 'highlight_text',
            check_marks: 'correctly checked answers and correctly unchecked incorrect answers',
            prompt: '',
            responses: []
          }
          break
        case ('drag_and_drop_cloze'):
          this.qtiJson = {
            questionType: 'drag_and_drop_cloze',
            prompt: '',
            correctResponses: [],
            distractors: [{ identifier: uuidv4(), value: '' }]
          }
          break
        case ('drop_down_table'):
          this.qtiJson = {
            questionType: 'drop_down_table',
            prompt: '',
            colHeaders: ['', ''],
            rows: [
              {
                header: '',
                selected: null,
                responses: [{ identifier: uuidv4(), value: '', correctResponse: true }]
              },
              {
                header: '',
                selected: null,
                responses: [{ identifier: uuidv4(), value: '', correctResponse: true }]
              }
            ]
          }
          break
        case ('multiple_response_grouping'):
          this.qtiJson = {
            questionType: 'multiple_response_grouping',
            prompt: '',
            headers: ['', ''],
            rows: [
              {
                grouping: '',
                responses: [{ identifier: uuidv4(), value: '', correctResponse: false }]
              },
              {
                grouping: '',
                responses: [{ identifier: uuidv4(), value: '', correctResponse: false }]
              }
            ]
          }
          break
        case ('matrix_multiple_choice'):
          this.qtiJson = {
            questionType: 'matrix_multiple_choice',
            prompt: '',
            headers: ['', '', ''],
            rows: [{ label: '', correctResponse: '' }]
          }
          break
        case ('matrix_multiple_response'):
          this.qtiJson = {
            questionType: 'matrix_multiple_response',
            prompt: '',
            colHeaders: ['', '', ''],
            rows: [{
              header: '',
              responses: [
                {
                  identifier: uuidv4(),
                  correctResponse: false
                }, {
                  identifier: uuidv4(),
                  correctResponse: false
                }
              ]
            }
            ]
          }
          break
        case ('multiple_response_select_n'):
          this.qtiJson = {
            questionType: 'multiple_response_select_n',
            prompt: '',
            numberToSelect: 0,
            responses: [{ identifier: uuidv4(), value: '', correctResponse: true },
              { identifier: uuidv4(), value: '', correctResponse: false }]
          }
          break
        case ('multiple_response_select_all_that_apply'):
          this.qtiJson = {
            questionType: 'multiple_response_select_all_that_apply',
            prompt: '',
            check_marks: 'correctly checked answers and correctly unchecked incorrect answers',
            responses: [{ identifier: uuidv4(), value: '', correctResponse: true },
              { identifier: uuidv4(), value: '', correctResponse: false }]
          }
          break
        case ('bow_tie'):
          this.qtiJson = {
            questionType: 'bow_tie',
            actionsToTake: [{ identifier: uuidv4(), value: '', correctResponse: true },
              { identifier: uuidv4(), value: '', correctResponse: true }],
            potentialConditions: [{ identifier: uuidv4(), value: '', correctResponse: true }],
            parametersToMonitor: [{ identifier: uuidv4(), value: '', correctResponse: true },
              { identifier: uuidv4(), value: '', correctResponse: true }]
          }
          break
        case ('numerical'):
          this.qtiJson = {
            questionType: 'numerical',
            prompt: '',
            correctResponse: {
              value: '',
              marginOfError: '0',
              toleranceType: 'absolute',
              relativeTolerance: '0'
            },
            feedback: {
              any: '',
              correct: '',
              incorrect: ''
            }
          }
          break
        case ('multi_numerical'):
          this.qtiJson = {
            questionType: 'multi_numerical',
            prompt: '',
            placeholders: [
              {
                value: '',
                toleranceType: 'absolute',
                absoluteTolerance: '0',
                relativeTolerance: '0'
              }
            ],
            correctResponse: {
              value: '',
              marginOfError: '0'
            }
          }
          break

        case ('matching'):
          this.qtiJson = { questionType: 'matching' }
          this.qtiJson.prompt = {}
          this.qtiJson.termsToMatch = []
          this.qtiJson.possibleMatches = []
          break
        case ('multiple_answers'):
        case ('multiple_choice'):
          let qtiJson
          qtiJson = simpleChoiceJson
          qtiJson.prompt = ''
          qtiJson.feedback = {}
          this.qtiPrompt = ''
          qtiJson.simpleChoice = [
            {
              identifier: uuidv4(),
              value: '',
              correctResponse: false,
              editorShown: true
            },
            {
              identifier: uuidv4(),
              value: '',
              correctResponse: false,
              editorShown: true
            }
          ]
          if (qtiJson['language']) {
            delete qtiJson['language']
          }
          this.simpleChoices = qtiJson.simpleChoice
          if (questionType === 'multiple_choice') {
            qtiJson.feedbackEditorShown = {}
            for (let i = 0; i < this.simpleChoices.length; i++) {
              this.simpleChoices[i].editorShown = true
              qtiJson.feedbackEditorShown[this.simpleChoices[i].identifier] = false
            }
            console.log(qtiJson.feedbackEditorShown)
          }

          this.correctResponse = ''
          qtiJson.questionType = questionType
          this.qtiJson = qtiJson
          this.$forceUpdate()
          break
        case ('true_false'):
          this.qtiJson = simpleChoiceJson
          this.qtiJson.prompt = ''
          this.qtiPrompt = ''
          this.qtiJson['questionType'] = 'true_false'
          this.qtiJson.simpleChoice = [
            {
              identifier: 'adapt-qti-true',
              value: 'True',
              correctResponse: false
            },
            {
              identifier: 'adapt-qti-false',
              value: 'False',
              correctResponse: false
            }
          ]
          this.simpleChoices = this.qtiJson.simpleChoice
          this.correctResponse = ''
          break
        case ('fill_in_the_blank'):
          this.qtiJson = {
            questionType: 'fill_in_the_blank',
            itemBody: { textEntryInteraction: '' }
          }
          this.simpleChoices = []
          break
        case ('drop_down_rationale_dyad'):
        case ('drop_down_rationale_triad'):
        case ('select_choice'):
          let dropDownRationaleType
          if (questionType.includes('drop_down_rationale')) {
            dropDownRationaleType = questionType.replace('drop_down_rationale_', '')
            questionType = 'drop_down_rationale'
          }
          const isTriad = dropDownRationaleType === 'triad'
          this.qtiJson = {
            questionType: questionType,
            'responseDeclaration': {
              'correctResponse': []
            },
            'itemBody': '',
            'inline_choice_interactions': {}
          }
          if (isTriad) {
            console.log('triad')
            this.qtiJson['inline_choice_interactions'] = {
              condition: [{ value: uuidv4(), text: '', correctResponse: true }],
              rationales: [{ value: uuidv4(), text: '', correctResponse: true }, {
                value: uuidv4(),
                text: '',
                correctResponse: true
              }]
            }
            this.qtiJson['questionType'] = 'drop_down_rationale_triad'
          }
          if (questionType === 'drop_down_rationale') {
            this.qtiJson.dropDownRationaleType = dropDownRationaleType
          }
          this.qtiJson.dropDownCloze = this.nativeType === 'nursing'
          break
        default:
          alert(`Need to update the code for ${questionType}`)
      }
      if (this.nursingQuestions.includes(questionType) || this.qtiJson.dropDownCloze) {
        this.qtiJson.feedback = {
          correct: '',
          incorrect: ''
        }
      }
      this.qtiPrompt = ''
    },
    goto (refName) {
      let element = this.$refs[refName]
      let top = element.offsetTop

      window.scrollTo(0, top)
    },
    deleteQtiTechnology () {
      this.qtiJson = {}
      this.correctResponse = ''
      this.simpleChoices = []
      this.qtiPrompt = ''
      this.$bvModal.hide(`modal-confirm-delete-qti-${this.modalId}`)
      this.editorGroups.find(editorGroup => editorGroup.id === 'technology').expanded = false
      this.questionForm.technology = 'text'
    },
    isCorrect (simpleChoice) {
      return this.correctResponse === simpleChoice.identifier
    },
    qtiType (qtiJson) {
      if (qtiJson.itemBody && !qtiJson.itemBody.simpleChoice) {

      }
    },
    toggleExpanded (id) {
      let editorGroup = this.editorGroups.find(group => group.id === id)
      if (id === 'non_technology_text' &&
        this.questionForm.technology === 'qti' &&
        !editorGroup.expanded) {
        this.$noty.info('For Native questions, please add any Open-Ended Content to the Prompt textarea.')
        return false
      }
      if (editorGroup && editorGroup.expanded) {
        switch (id) {
          case ('technology'):
            if (this.questionFormTechnology === 'qti') {
              this.$bvModal.show(`modal-confirm-delete-qti-${this.modalId}`)
              return false
            }
            if (this.questionForm.technology !== 'text') {
              this.$noty.info('If you would like to hide the auto-graded technology input area, make sure that no technology is chosen.')
              return false
            }
            break
          case ('a11y_auto_graded_question_id'):
            if (this.questionForm.a11y_auto_graded_question_id) {
              this.$noty.info('If you would like to hide the accessible alternative auto-grade input area, please first empty the alternative auto-grade input area.')
              return false
            }
            break
          default:
            if (this.questionForm[id] && this.questionForm[id].length) {
              this.$noty.info(`If you would like to hide the ${editorGroup.label} input area, please first remove any text.`)
              return false
            }
        }
      }
      this.editorGroups.find(group => group.id === id).expanded = !editorGroup.expanded
    },
    openCreateAutoGradedTechnologyCode (value) {
      if (this.isLoadingEdit) {
        return
      }
      this.questionForm.new_auto_graded_code = false
      this.webworkEditorShown = false
      this.questionForm.a11y_auto_graded_question_id = null
      this.existingQuestionFormTechnology = 'text'
      switch (value) {
        case ('webwork'):
          this.webworkEditorShown = true
          this.questionFormTechnology = 'webwork'
          this.questionForm.technology = 'webwork'
          this.questionForm.new_auto_graded_code = 'webwork'
          this.questionForm.response_format = null
          this.webworkCodeKey++
          break
        case ('forge'):
          this.questionForm.technology = 'qti'
          this.questionFormTechnology = 'qti'
          this.qtiQuestionType = 'forge'
          this.initQTIQuestionType('forge')
          this.nativeType = 'forge'
          break
        case ('qti'):
          if (this.questionForm.non_technology_text) {
            this.newAutoGradedTechnology = null
            this.$noty.info('Please first remove any Open-Ended Content.  You can always place additional content in the Native question\'s Prompt.')
            return false
          }
          this.questionForm.technology = 'qti'
          this.nativeType = 'basic'
          this.qtiQuestionType = 'multiple_choice'
          this.initQTIQuestionType('multiple_choice')
          this.editorGroups.find(group => group.id === 'non_technology_text').expanded = false
          break
        case ('h5p'):
          this.questionForm.technology = 'h5p'
          this.createAutoGradedRedirectTechnology = 'h5p'
          break
        case ('imathas'):
          this.createAutoGradedRedirectTechnology = 'imathas'
          this.questionForm.technology = 'imathas'
          break
        case ('sketcher'):
          if (this.questionForm.non_technology_text) {
            this.newAutoGradedTechnology = null
            this.$noty.info('Please first remove any Open-Ended Content.  You can always place additional content in the Sketcher question\'s Prompt.')
            return false
          }
          this.questionForm.technology = 'sketcher'
          break
        case null:
          this.questionForm.technology = 'text'
          return false
        default:
          alert(`${value} is not a valid option.`)
          break
      }
    },
    reloadCreateQuestionSavedQuestionsFolders (type) {
      this.savedQuestionsFolderKey++
    },
    setMyCoursesFolder (myCoursesFolder) {
      this.questionForm.folder_id = myCoursesFolder
      this.questionForm.errors.clear('folder_id')
    },
    async resetQuestionForm (questionType) {
      await this.getDefaultSubject()
      if (this.subject) {
        await this.getLearningOutcomes(this.subject)
      }
      let folderId
      folderId = this.questionForm.folder_id
      this.questionFormTechnology = 'text'
      this.qtiPrompt = ''
      this.simpleChoiceToRemove = {}
      this.correctResponse = ''
      this.simpleChoices = []
      this.qtiJson = {}
      this.questionForm.rubric_categories = []
      let nonTechnologyText = this.questionForm.non_technology_text
      if (questionType === 'exposition') {
        this.questionForm.technology = this.questionFormTechnology = 'text'
        this.questionForm.technology_id = ''
        this.questionForm.non_technology_text = this.switchingType ? nonTechnologyText : ''
        this.questionForm.text_question = null
        this.questionForm.a11y_auto_graded_question_id = null
        this.questionForm.answer_html = null
        this.questionForm.solution_html = null
        this.questionForm.hint = null
      } else {
        if (this.isEdit) {
          // switching from exposition to assessment so it's OK!
        } else {
          this.questionForm = new Form(defaultQuestionForm)
          this.questionForm.non_technology_text = this.switchingType ? nonTechnologyText : ''
          this.questionForm.source_url = window.location.origin
          this.webworkAttachments = []
          this.webworkUploadFile = null
          this.webworkUploadFilename = ''
          this.webworkUploadProgress = 0
          this.webworkUploading = false
          this.webworkEditorShown = false
          this.questionForm.author = this.user.first_name + ' ' + this.user.last_name
          this.newAutoGradedTechnology = null
          this.existingQuestionFormTechnology = 'text'
        }
      }
      if (this.nursing) {
        this.initNonBasicQTIQuestion()
      }
      this.questionForm.question_type = questionType
      this.questionForm.folder_id = folderId
    },
    getQuestionType () {
      if (this.questionForm.question_type === 'auto_graded') {
        return 'Auto-Graded'
      } else if (this.questionForm.question_type === 'open_ended') {
        return 'Open-Ended'
      } else if (this.questionForm.question_type === 'frankenstein') {
        return 'Frankenstein'
      } else {
        return 'Question type not valid; please contact us.'
      }
    },
    validateImagesHaveAlts () {
      let imgNeedsAltSrc = false
      $('.cke_contents').each(function () {
        let images = $(this).find('iframe').contents().find('img')

        if ($(this).find('iframe').contents().find('img.cke_widget_element')) {
          images.each(function () {
            let html = $(this)[0].outerHTML
            if ($(this).attr('src', 'data:image/gif;base64,R0lGODlhAQABAPABAP///wAAACH5BAEKAAAALAAAAAABAAEAAAICRAEAOw==')) {
              // ignore the ckeditor handle resizer
              return
            }
            if (html.includes('alt=""') && !imgNeedsAltSrc) {
              const regex = /data-cke-saved-src="(.*?)"/g
              imgNeedsAltSrc = html.match(regex)
            }
          })
        }
      })
      if (imgNeedsAltSrc) {
        this.imgNeedsAltSrc = imgNeedsAltSrc[0].replaceAll('amp;', '').replace('data-cke-saved-src="', '').replace('"', '')
        this.$bvModal.show('modal-img-needs-alt')
        return false
      }
      return true
    },
    async previewQuestion () {
      if (['marker', 'submit-molecule'].includes(this.qtiQuestionType)) {
        this.previewingQuestion = true
        this.receivedStructure = false
        const iframe = document.getElementById('sketcher')
        iframe.contentWindow.postMessage('save', '*')
        await this.handleGetStructure()
      }
      if (!this.validateImagesHaveAlts()) {
        return false
      }
      if (this.questionForm.technology !== 'text' &&
        !this.questionForm.technology_id &&
        !this.questionForm.webwork_code &&
        this.questionForm.technology !== 'qti') {
        if (this.webworkEditorShown) {
          let message = `WebWork code is required to preview this question.`
          this.questionForm.errors.set('webwork_code', message)
        } else {
          let identifier = this.questionForm.technology === 'webwork' ? 'A File Path' : 'An ID'
          let message = `${identifier} is required to preview this question.`
          this.questionForm.errors.set('technology_id', message)
        }
        return false
      }
      this.processingPreview = true
      this.showQtiAnswer = false
      this.qtiJsonQuestionViewerKey++
      if (this.questionForm.webwork_code) {
        this.$forceUpdate()
        this.questionForm.session_identifier = this.sessionIdentifier
        this.questionForm.pending_webwork_attachments = this.webworkAttachments.filter(attachment => attachment.status === 'pending')
      }
      try {
        if (this.questionForm.technology !== 'qti') {
          const { data } = await this.questionForm.post('/api/questions/preview')
          if (data.type === 'error') {
            this.$noty.error(data.message)
            this.processingPreview = false
            return false
          }
          this.questionToView = data.question
        } else {
          switch (this.qtiQuestionType) {
            case ('three_d_model_multiple_choice'):
              this.$forceUpdate()
              break
            case ('flashcard'):
              const cardData = this.$refs.flashcard.getCardData()
              const frontType = String(cardData.frontType)
              const backType = String(cardData.backType)
              if (frontType === 'text_media' || frontType === 'media') {
                cardData.frontMediaUrl = this.$refs.flashcard.form.frontMediaUrl
              }
              if (backType === 'text_media' || backType === 'media') {
                cardData.backMediaUrl = this.$refs.flashcard.form.backMediaUrl
              }
              this.qtiJson = {
                questionType: 'flashcard',
                card: cardData
              }
              this.previewingQuestion = true
              this.$forceUpdate()
              break
            case ('accounting_report'):
            case ('accounting_journal_entry'):
            case ('accounting_multi_part_computation'):
              this.$forceUpdate()
              break
            case ('discuss_it'):
              this.qtiJson.media_uploads = this.questionForm.media_uploads
              this.previewingQuestion = true
              this.$forceUpdate()
              break
            case ('matching'):
              this.qtiJson.termsToMatch = this.$refs.matching.termsToMatch
              console.log(this.qtiJson.termsToMatch)
              this.qtiJson.possibleMatches = this.$refs.matching.possibleMatches
              console.log(this.qtiJson.possibleMatches)
              if (this.$refs.matching.possibleMatches) {
                console.log(this.$refs.matching.possibleMatches)
                for (let i = 0; i < this.$refs.matching.matchingDistractors.length; i++) {
                  console.log(this.$refs.matching.matchingDistractors[i])
                  let matchingDistractor = this.$refs.matching.matchingDistractors[i]
                  let possibleMatch = this.qtiJson.possibleMatches.find(possibleMatch => possibleMatch.identifier === matchingDistractor.identifier)
                  if (possibleMatch) {
                    possibleMatch.matchingTerm = matchingDistractor.matchingTerm
                  }
                }
              }
              break
            case ('fill_in_the_blank'):
              this.qtiJson.responseDeclaration = {}
              this.qtiJson.responseDeclaration.correctResponse = this.$refs.fillInTheBlank.getFillInTheBlankResponseDeclarations()
              break

            case ('drag_and_drop_cloze'):
              this.qtiJson.selectOptions = [{ value: null, text: 'Please choose an option' }]
              let responses = this.qtiJson.correctResponses.concat(this.qtiJson.distractors)
              for (let i = 0; i < responses.length; i++) {
                let response = responses[i]
                this.qtiJson.selectOptions.push({ value: response.identifier, text: response.value })
              }
              break
          }

          this.$forceUpdate()
          this.questionToView = this.qtiJson
          if (this.questionToView.questionType !== 'drop_down_rationale_triad' &&
            this.qtiQuestionType.includes('drop_down_rationale')) {
            this.questionToView.questionType = 'drop_down_rationale'
          }
        }

        this.showQtiAnswer = false
        this.$bvModal.show(this.modalId)
        this.$nextTick(() => {
          this.typesetMath(document.getElementById(this.modalId))
        })
        console.log(this.questionToView)
      } catch (error) {
        this.$noty.error(error.message)
      }
      this.processingPreview = false
    },
    async saveQuestion () {
      if (window.self !== window.top) {
        window.parent.postMessage('scroll-to-top', '*')
      }
      try {
        this.switchingType = false
        this.savingQuestion = true
        this.questionForm.assignment_id = this.assignmentId
        if (this.isEdit) {
          this.questionForm.revision_action = this.revisionAction
        }
        const { data } = this.isEdit
          ? await this.questionForm.patch(`/api/questions/${this.questionForm.id}`)
          : await this.questionForm.post('/api/questions')
        if (data.type === 'error' &&
          (data.reason_for_edit_error || data.automatically_update_revision_error || data.changes_are_topical_error)) {
          if (data.reason_for_edit_error) {
            this.questionForm.errors.set('reason_for_edit', data.reason_for_edit_error)
          }
          if (data.automatically_update_revision_error) {
            this.questionForm.errors.set('automatically_update_revision', data.automatically_update_revision_error)
          }

          if (data.changes_are_topical_error) {
            this.questionForm.errors.set('changes_are_topical', data.changes_are_topical_error)
          }
          if (window.self !== window.top) {
            window.parent.postMessage('scroll-to-top', '*')
          }
          this.$bvModal.show('modal-reason-for-edit')
          this.savingQuestion = false
          return false
        }
        this.$noty[data.type](data.message)
        this.savingQuestion = false
        if (data.type === 'success') {
          this.$bvModal.hide('modal-reason-for-edit')
          if (this.assignmentId) {
            this.parentGetMyQuestions()
          }
          if (!this.isEdit) {
            this.goto('top-of-form')
          }
          if (this.$route.name !== 'empty_learning_tree_node') {
            await this.resetQuestionForm('assessment')
          }
          this.tag = ''
          this.questionForm.tags.length = 0
          if (this.isEdit) {
            this.$bvModal.hide(`modal-edit-question-${this.questionToEdit.id}`)
            this.parentGetMyQuestions()
          }
        }
      } catch (error) {
        if (!error.message.includes('status code 422')) {
          this.$noty.error(error.message)
          this.savingQuestion = false
        } else {
          console.log(this.questionForm.errors)
          this.$nextTick(() => fixInvalid())
          let errors = JSON.parse(JSON.stringify(this.questionForm.errors)).errors
          let formattedErrors = []
          for (const property in errors) {
            console.error(errors[property])
            console.error(property)
            switch (property) {
              case ('qti_json'):
                if (this.qtiQuestionType === 'flashcard') {
                  const flashcardErrors = JSON.parse(errors[property])
                  this.$refs.flashcard.setErrors(flashcardErrors)
                  formattedErrors.push('Please fix the flashcard errors.')
                }
                if (this.qtiQuestionType === 'accounting_journal_entry') {
                  formattedErrors.push('Please fix the errors associated with your journal entries.')
                } else if (this.qtiQuestionType === 'accounting_report') {
                  formattedErrors.push('Please fix the errors associated with your accounting report.')
                } else if (this.qtiQuestionType === 'accounting_multi_part_computation') {
                  formattedErrors.push('Please fix the errors associated with your multi-part computation.')
                }
                break
              case ('parameters'):
                formattedErrors.push('Please fix the errors associated with your 3D Model parameters.')
                this.threeDModelParameterErrors = errors['parameters']
                break
              case ('solution_structure'):
                this.threeDModelSolutionStructureErrors = errors['solution_structure'] ? errors['solution_structure'][0] : ''
                if (this.threeDModelSolutionStructureErrors) {
                  formattedErrors.push(this.threeDModelSolutionStructureErrors)
                }
                break
              case ('rubric_items'):
                formattedErrors.push('Not all of your rubric criterion are valid.')
                this.questionForm.errors.errors.rubric_items = JSON.parse(this.questionForm.errors.errors.rubric_items)
                break
              case ('qti_randomize_order'):
                formattedErrors.push('Please specify whether you would like to randomize the order of the responses.')
                break
              case ('rubric_categories'):
                formattedErrors.push('Please fix the Rubric errors.')
                break
              case ('qti_select_choice_condition'):
                formattedErrors.push('Please fix the Condition errors.')
                break
              case ('qti_select_choice_rationales'):
                formattedErrors.push('Please fix the Rationale errors.')
                break
              case ('distractors'):
                formattedErrors.push('Please fix the Distractor errors.')
                break
              case ('actions_to_take'):
                formattedErrors.push('Please fix the Actions To Take errors.')
                break
              case ('potential_conditions'):
                formattedErrors.push('Please fix the Potential Conditions errors.')
                break
              case ('parameters_to_monitor'):
                formattedErrors.push('Please fix the Parameters To Monitor errors.')
                break
              case ('headers'):
                formattedErrors.push('Please fix the table header errors.')
                break
              case ('rows'):
                formattedErrors.push('Please fix the row errors.')
                break
              case ('colHeaders'):
                formattedErrors.push('Please fix the column errors.')
                break
              case ('responses'):
                formattedErrors.push('Please fix the errors with the responses.')
                break
              case ('atoms_and_bonds'):
                formattedErrors.push('Please fix the Score Adjustment Percent errors associated with your Marker question.')
                break
              case ('placeholders'):
                formattedErrors.push('Please fix the numerical blank errors.')
                this.$refs.Numerical.setErrors(JSON.parse(errors[property]))
                break
              default:
                formattedErrors.push(errors[property][0])
            }
          }
          this.allFormErrors = [...new Set(formattedErrors)]
          this.savingQuestion = false
          this.questionsFormKey++
          this.$nextTick(() => {
            this.$bvModal.show(`modal-form-errors-questions-form-${this.questionsFormKey}`)
            this.updateTabInvalids()
          })
        }
      }
    },
    removeLearningOutcome (chosenLearningOutcome) {
      let chosenLearningOutcomeLabel = chosenLearningOutcome.label ? chosenLearningOutcome.label : this.getLearningOutcomeLabel(chosenLearningOutcome)

      this.questionForm.learning_outcomes = chosenLearningOutcome.id
        ? this.questionForm.learning_outcomes.filter(learningOutcome => learningOutcome.id !== chosenLearningOutcome.id)
        : this.questionForm.learning_outcomes.filter(learningOutcome => learningOutcome !== chosenLearningOutcome)
      this.$noty.info(`You have removed the learning outcome "<strong>${chosenLearningOutcomeLabel}</strong>"`)
    },
    removeTag (chosenTag) {
      this.questionForm.tags = this.questionForm.tags.filter(tag => tag !== chosenTag)
      this.$noty.info(`${chosenTag} has been removed.`)
    },
    addLearningOutcome (learningOutcome) {
      if (!this.questionForm.learning_outcomes.includes(learningOutcome.id)) {
        this.questionForm.learning_outcomes.push(learningOutcome.id)
      } else {
        this.$noty.info(`${learningOutcome.label} is already on your list of learning outcomes.`)
      }
      this.tag = ''
    },
    addTag () {
      if (!this.questionForm.tags.includes(this.tag)) {
        this.questionForm.tags.push(this.tag)
      } else {
        this.$noty.info(`${this.tag} is already on your list of tags.`)
      }
      this.tag = ''
    },
    fixEmptyParagraphs (type) {
      const emptyParagraph = '<p>&nbsp;</p>'
      console.log('removing empty paragraphs')
      switch (type) {
        case ('discuss_it_text'):
          if (this.discussItTextForm.text) {
            console.log('discuss-it-text')
            this.discussItTextForm.text = this.discussItTextForm.text.replaceAll(emptyParagraph, '')
            this.discussItTextForm.text = this.discussItTextForm.text.trim()
            console.log('Empty paragraphs in the dicussit-text text have been removed')
          }
          break
        case ('non_technology_text'):
          if (this.questionForm.non_technology_text) {
            console.log('non-technology-text')
            this.questionForm.non_technology_text = this.questionForm.non_technology_text.replaceAll(emptyParagraph, '')
            this.questionForm.non_technology_text = this.questionForm.non_technology_text.trim()
            console.log('Empty paragraphs in the non-technology text have been removed')
          }
          break
        case ('qti_json_text_entry_interaction'):
          if (this.qtiJson.itemBody.textEntryInteraction) {
            this.qtiJson.itemBody.textEntryInteraction = this.qtiJson.itemBody.textEntryInteraction.replaceAll(emptyParagraph, '')
            this.qtiJson.itemBody.textEntryInteraction = this.qtiJson.itemBody.textEntryInteraction.trim()
            console.log('Empty paragraphs in the item body have been removed')
          }
          break
        case ('qti_json_item_body'):
          if (this.qtiJson.itemBody) {
            console.log('item body')
            this.qtiJson.itemBody = this.qtiJson.itemBody.replaceAll(emptyParagraph, '')
            this.qtiJson.itemBody = this.qtiJson.itemBody.trim()
            console.log('Empty paragraphs in the item body have been removed')
          }
          break
        case ('qti_json_prompt'):
          if (this.qtiJson.prompt) {
            console.log('item prompt')
            this.qtiJson.prompt = this.qtiJson.prompt.replaceAll(emptyParagraph, '')
            this.qtiJson.prompt = this.qtiJson.prompt.trim()
            console.log('Empty paragraphs in the prompt have been removed')
          }
      }
    },
    handleFixCKEditor () {
      fixCKEditor(this)
    },
    onCKEditorNamespaceLoaded (CKEDITOR) {
      CKEDITOR.addCss('.cke_editable { font-size: 15px; }')
    }
  }
}
</script>

<style scoped>
.numerical-input {
  width: 150px;
}

.thick-black-border {
  border: 2px solid black;
}

.thick-black-border-content {
  margin-left: 7px;
  margin-right: 7px;
}

</style>
