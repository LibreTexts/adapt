<template>
  <div>
    <QtiJsonAnswerViewer v-if="questionToView.question_id"
                         :modal-id="questionToView.question_id"
                         :qti-json="questionToView.qti_answer_json"
    />
    <b-modal id="modal-discussion-questions-exist"
             title="Discuss-it Question Settings"
             no-close-on-esc
             no-close-on-backdrop
             size="lg"
    >
      <p>
        You are adding <span v-show="questionsToAdd.length>1">at least</span> one Discuss-it question. You can use the
        settings of the imported
        questions or reset the settings to the default ADAPT settings. After adding the question<span
        v-show="questionsToAdd.length>1"
      >s</span>, you can always
        manually adjust
        any settings.
      </p>
      <b-form-group
        label-cols-sm="4"
        label-cols-lg="3"
        label="Reset Settings to Default"
        label-for="reset_discuss_it_settings_to_default"
      >
        <b-form-radio-group
          id="reset_discuss_it_settings_to_default"
          v-model="resetDiscussItSettingsToDefault"
          class="mt-2"
        >
          <b-form-radio value="1">
            Yes
          </b-form-radio>
          <b-form-radio value="0">
            No
          </b-form-radio>
        </b-form-radio-group>
      </b-form-group>
      <template #modal-footer>
        <b-button
          variant="primary"
          size="sm"
          class="float-right"
          @click="addQuestions(questionsToAdd)"
        >
          Submit
        </b-button>
      </template>
    </b-modal>
    <b-modal id="modal-formatted-question-types"
             title="Choose Type"
             size="xl"
    >
      <FormattedQuestionTypeRadio :key="allQuestionsTechnology"
                                  :formatted-question-types-options-by-technology="formattedQuestionTypesOptionsByTechnology"
                                  :technology="allQuestionsTechnology"
                                  :formatted-question-type="formattedQuestionType"
                                  :interactive-formatted-types="interactiveH5PFormattedTypes"
                                  @setFormattedQuestionType="setFormattedQuestionType"
      />
      <template #modal-footer="{ ok }">
        <b-button size="sm" variant="primary" @click="$bvModal.hide('modal-formatted-question-types')">
          OK
        </b-button>
      </template>
    </b-modal>
    <b-modal id="modal-cannot-reorder"
             title="Cannot re-order questions"
             @hidden="getCollection(questionSource)"
    >
      Questions can be dragged into new folders on the left. However, you cannot re-order the questions within a folder.
      <template #modal-footer="{ ok }">
        <b-button size="sm" variant="primary" @click="$bvModal.hide('modal-cannot-reorder')">
          OK
        </b-button>
      </template>
    </b-modal>
    <b-modal
      :id="`modal-share-${questionToView.question_id}`"
      ref="modal_share"
      title="Share"
      size="lg"
    >
      <div class="mb-2">
        <span class="font-weight-bold">Question ID: </span><span id="questionID">{{ questionToView.question_id }}</span>
        <span class="text-info">
          <a
            href=""
            class="pr-1"
            aria-label="Copy question ID"
            @click.prevent="doCopy('questionID', 'The question ID has been copied to your clipboard.')"
          >
            <font-awesome-icon :icon="copyIcon"/>
          </a>
        </span>
      </div>
      <div v-if="questionToView.library_page_id" class="mb-2">
        <span class="font-weight-bold">Libretexts ID:</span> <span id="libretextsID">
          {{ questionToView.library_page_id }}</span>
        <span class="text-info">
          <a
            href=""
            class="pr-1"
            aria-label="Copy Libretexts ID"
            @click.prevent="doCopy('libretextsID')"
          >
            <font-awesome-icon :icon="copyIcon"/>
          </a>
        </span>
      </div>
      <div v-if="questionToView.technologySrc" class="mb-2">
        <span class="font-weight-bold">Technology URL: </span><span id="technologySrc"
                                                                    v-html="technologySrc"
      />
      </div>
      <a v-if="questionToView.technology === 'webwork' && questionToView.webwork_code !== null"
         class="btn btn-sm btn-outline-primary link-outline-primary-btn"
         :href="`/api/questions/export-webwork-code/${questionToView.id}`"
      >
        Export Code
      </a>

      <template #modal-footer="{ ok }">
        <b-button size="sm" variant="primary" @click="$bvModal.hide(`modal-share-${questionToView.question_id}`)">
          OK
        </b-button>
      </template>
    </b-modal>
    <SavedQuestionsFolders
      v-show="false"
      ref="moveOrRemoveQuestionsMyFavorites"
      :key="`move-or-remove-questions-my-favorites-${questionSource}`"
      :question-source-is-my-favorites="questionSource === 'my_favorites'"
      :type="savedQuestionsFoldersType"
      :folder-level="folderLevel"
      :assignment="chosenAssignment"
      :modal-id="`modal-add-saved-questions-folder-${questionSource}`"
      :create-modal-add-saved-questions-folder="true"
      @savedQuestionsFolderSet="setSavedQuestionsFolder"
      @getCurrentAssignmentQuestionsBasedOnChosenAssignmentOrSavedQuestionsFolder="getCurrentAssignmentQuestionsBasedOnChosenAssignmentOrSavedQuestionsFolder"
      @reloadSavedQuestionsFolders="getCollection"
      @resetFolderAction="resetFolderAction"
      @removeMyFavoritesQuestion="removeMyFavoritesQuestion"
      @reloadMyFavoritesOptions="reloadMyFavoritesOptions"
      @updateTopicInList="updateTopicInList"
      @addTopicToList="addTopicToList"
      @removeTopic="removeTopic"
    />
    <b-modal
      id="modal-bulk-move-to-new-topic"
      title="Bulk move questions to new topic"
    >
      <b-form-select id="topics"
                     v-model="topicToMoveQuestionsTo"
                     :options="filteredTopicsOptions"
      />

      <template #modal-footer>
        <b-button
          size="sm"
          class="float-right"
          @click="$bvModal.hide('modal-bulk-move-to-new-topic')"
        >
          Cancel
        </b-button>
        <b-button
          variant="primary"
          size="sm"
          class="float-right"
          @click="handleBulkMoveToNewTopic(topicToMoveQuestionsTo)"
        >
          Move
        </b-button>
      </template>
    </b-modal>
    <b-modal
      id="modal-init-remove-question-from-favorites-folder"
      :title="`Remove question from My Favorites`"
    >
      <p>
        Please confirm that you would like to remove the question <span class="font-weight-bold"
      >{{ questionToRemoveFromFavoritesFolder.title }}</span> from
        the My Favorites folder <span class="font-weight-bold"
      >{{ questionToRemoveFromFavoritesFolder.my_favorites_folder_name }}</span>.
      </p>
      <template #modal-footer>
        <b-button
          size="sm"
          class="float-right"
          @click="$bvModal.hide('modal-init-remove-question-from-favorites-folder')"
        >
          Cancel
        </b-button>
        <b-button
          variant="danger"
          size="sm"
          class="float-right"
          @click="removeMyFavoritesQuestion(questionToRemoveFromFavoritesFolder.my_favorites_folder_id,questionToRemoveFromFavoritesFolder.question_id)"
        >
          Remove
        </b-button>
      </template>
    </b-modal>
    <b-modal
      :id="`modal-save-to-my-favorites-${questionSource}`"
      ref="saveToMyFavorites"
      title="Save Question To My Favorites"
    >
      <SavedQuestionsFolders
        ref="savedQuestionsFolders"
        :key="`save-to-my-favorites-${questionSource}`"
        :type="'my_favorites'"
        :create-modal-add-saved-questions-folder="true"
        :modal-id="`modal-add-saved-questions-folder-save-to-my-favorites`"
        :init-saved-questions-folder="savedQuestionsFolder"
        :question-source-is-my-favorites="questionSource === 'my_favorites'"
        @savedQuestionsFolderSet="setSavedQuestionsFolder"
        @getCurrentAssignmentQuestionsBasedOnChosenAssignmentOrSavedQuestionsFolder="getCurrentAssignmentQuestionsBasedOnChosenAssignmentOrSavedQuestionsFolder"
        @reloadSavedQuestionsFolders="getCollection"
        @resetFolderAction="resetFolderAction"
        @reloadMyFavoritesOptions="reloadMyFavoritesOptions"
      />
      <template #modal-footer>
        <b-button
          size="sm"
          class="float-right"
          @click="$bvModal.hide(`modal-save-to-my-favorites-${questionSource}`)"
        >
          Cancel
        </b-button>
        <b-button
          variant="primary"
          size="sm"
          class="float-right"
          @click="saveMyFavoritesQuestions()"
        >
          Submit
        </b-button>
      </template>
    </b-modal>
    <b-modal
      :id="`question-bank-view-questions-${questionSource}`"
      size="lg"
      :hide-footer="true"
      @show="questionBankModalShown = true"
      @hide="questionBankModalShown = false;resetBulkActionData()"
    >
      <template #modal-header>
        <div>
          <h2 class="h5 modal-title">
            {{ questionToView.title }}
          </h2>
          <div>
            <small>ADAPT ID: <span :id="`adapt-id-in-title-${questionToView.id}`">{{ questionToView.id }}</span></small>
            <span class="text-info">
              <a href=""
                 aria-label="Copy ADAPT ID"
                 @click.prevent="doCopy(`adapt-id-in-title-${questionToView.id}`)"
              >
                <font-awesome-icon :icon="copyIcon"/>
              </a>
            </span>
          </div>
        </div>
        <button type="button" aria-label="Close"
                class="close"
                @click="$bvModal.hide(`question-bank-view-questions-${questionSource}`)"
        >
          ×
        </button>
      </template>

      <div class="pb-2">
        <b-button v-if="questionToView.qti_json
        && !isDiscussIt(questionToView)
        && !isFlashcard(questionToView)
        && isQtiOrForgeWithQtiAnswerSolution(questionToView, 'qti_json')"
                  size="sm"
                  variant="outline-info"
                  @click="$bvModal.show(`qti-answer-${questionToView.id}`)"
        >
          View Correct Answer
        </b-button>
        <SolutionFileHtml v-if="questionToView.solution_html"
                          :key="questionToView.id"
                          :questions="[questionToView]"
                          :current-page="1"
                          assignment-name="Question"
        />
        <span v-if="withinAssignment" class="pr-2">
          <span v-if="questionToView.in_current_assignment">
            <b-button
              variant="danger"
              size="sm"
              @click="questionToRemove = questionToView; openRemoveQuestionModal(questionToView)"
            >
              Remove From Assignment
            </b-button>
          </span>
          <span v-show="!questionToView.in_current_assignment">
            <b-button
              variant="primary"
              size="sm"

              @click="addQuestions([questionToView])"
            >
              Add To Assignment
            </b-button>
          </span>
        </span>

        <span v-show="!questionToView.my_favorites_folder_id" class="p-1">
          <b-tooltip
            :target="`add-to-my-favorites-${questionToView.question_id}`"
            delay="500"
            triggers="hover focus"
            title="Add to My Favorites"
          >
            Add to My Favorites folder
          </b-tooltip>
          <span :id="`add-to-my-favorites-${questionToView.question_id}`">
            <font-awesome-icon :icon="heartIcon"
                               size="lg"
                               class="text-muted"
                               @click="initSaveToMyFavorites([questionToView.question_id])"
            />
          </span>
        </span>
        <span v-if="questionSource !== 'my_favorites' && questionToView.my_favorites_folder_id" class="p-1">
          <b-tooltip
            :target="`remove-from-my-favorites-${questionToView.question_id}`"
            delay="500"
            triggers="hover focus"
            :title="`Remove from ${questionToView.my_favorites_folder_name}`"
          >
            Remove from the My Favorites folder {{
              questionToView.my_favorites_folder_name
            }}
          </b-tooltip>
          <span :id="`remove-from-my-favorites-${questionToView.question_id}`">
            <font-awesome-icon
              :icon="heartIcon"
              size="lg"
              class="text-danger"
              @click="removeMyFavoritesQuestion(questionToView.my_favorites_folder_id, questionToView.question_id)"
            />
          </span>
        </span>
        <GetQuestionsActions :key="`get-questions-actions-${questionSourceKey}`"
                             :assignment-question="questionToView"
                             :size="'lg'"
                             :show-plus-minus-for-add-remove="false"
                             :show-heart="false"
                             :show-trash="false"
                             :heart-icon="heartIcon"
                             :question-source="questionSource"
                             :within-assignment="withinAssignment"
                             :assignment-questions="assignmentQuestions"
                             @removeMyFavoritesQuestion="removeMyFavoritesQuestion"
                             @initSaveToMyFavorites="initSaveToMyFavorites"
                             @initRemoveAssignmentQuestion="initRemoveAssignmentQuestion"
                             @addQuestions="addQuestions"
                             @reloadCurrentAssignmentQuestions="reloadQuestionsAfterEdit"
        />
      </div>
      <ViewQuestions :key="`view-selected-questions-clicked-${numViewSelectedQuestionsClicked}-${questionSourceKey}`"
                     :question-ids-to-view="selectedQuestionIds"
                     :showSolutionFileHTML="false"
                     @questionToViewSet="setQuestionToView"
      />
    </b-modal>
    <b-modal
      id="modal-remove-question"
      ref="modal"
      title="Confirm Remove Question"
    >
      <RemoveQuestion :beta-assignments-exist="betaAssignmentsExist" :question-to-remove="questionToRemove"/>
      <template #modal-footer>
        <b-button
          size="sm"
          class="float-right"
          @click="$bvModal.hide('modal-remove-question')"
        >
          Cancel
        </b-button>
        <b-button
          variant="danger"
          size="sm"
          class="float-right"
          @click="submitRemoveQuestion()"
        >
          Remove question
        </b-button>
      </template>
    </b-modal>
    <b-modal
      id="modal-upload-file"
      ref="solutionFileInput"
      title="Upload File"
      ok-title="Submit"
      size="lg"
      @ok="handleOk"
    >
      <b-form ref="form">
        <p>Accepted file types are: {{ getAcceptedFileTypes() }}.</p>
        <b-form-file
          ref="solutionFileInput"
          v-model="uploadFileForm.solutionFile"
          placeholder="Choose a file or drop it here..."
          drop-placeholder="Drop file here..."
          :accept="getAcceptedFileTypes()"
        />
        <div v-if="uploading">
          <b-spinner small type="grow"/>
          Uploading file...
        </div>
        <input type="hidden" class="form-control is-invalid">
        <div class="help-block invalid-feedback">
          {{ uploadFileForm.errors.get('solutionFile') }}
        </div>
      </b-form>
    </b-modal>
    <b-modal
      id="modal-non-h5p"
      ref="h5pModal"
      title="Non-H5P assessments in clicker assignment"
    >
      <b-alert :show="true" variant="danger">
        <span class="font-weight-bold">
          {{
            h5pText()
          }}
        </span>
      </b-alert>
      <template #modal-footer="{ ok }">
        <b-button size="sm" variant="primary" @click="$bvModal.hide('modal-non-h5p')">
          OK
        </b-button>
      </template>
    </b-modal>
    <div class="vld-parent">
      <loading :active.sync="isLoading"
               :can-cancel="true"
               :is-full-page="true"
               :width="128"
               :height="128"
               color="#007BFF"
               background="#FFFFFF"
      />
      <div v-show="!isLoading">
        <PageTitle :title="title"/>
        <b-container v-if="withinAssignment">
          <AssessmentTypeWarnings :assessment-type="assessmentType"
                                  :open-ended-questions-in-real-time="openEndedQuestionsInRealTime"
                                  :learning-tree-questions-in-non-learning-tree="learningTreeQuestionsInNonLearningTree"
                                  :non-learning-tree-questions="nonLearningTreeQuestions"
                                  :beta-assignments-exist="betaAssignmentsExist"
                                  :h5p-questions-with-anonymous-users="h5pQuestionsWithAnonymousUsers"
          />
          <b-row align-h="end">
            <b-button
              size="sm"
              variant="link"
              class="view-questions-btn"
              @click="getStudentView(assignmentId)"
            >
              <b-icon-eye/>
              View Questions
            </b-button>
          </b-row>
          <hr>
        </b-container>
        <div>
          <div v-if="isPointsByQuestionWeightAndSubmissionsExist">
            <b-alert show>
              <span class="font-weight-bold">
                You cannot add or remove questions to this assignment since there are already submissions and this assignment computes points using question weights.
              </span>
            </b-alert>
          </div>
          <div v-else>
            <b-tabs content-class="mt-3" class="questions-get-tabs">
              <b-tab id="question_bank_tab"
                     title="Question Bank" active
                     @click="showQuestions = false;"
              >
                <b-container>
                  <b-row>
                    <b-col v-if="withinAssignment || ['my_questions','my_favorites'].includes(questionSource)" cols="4">
                      <b-row v-if="withinAssignment && !isFormative && !isLoading" class="pb-2">
                        <b-form-select id="collections"
                                       v-model="questionSource"
                                       :options="questionSourceOptions"
                                       @change="initGetQuestionSource($event)"
                        />
                      </b-row>
                      <b-row class="pb-4">
                        <b-form-select v-show="questionChosenFromAssignment()"
                                       id="collections"
                                       v-model="collection"
                                       :disabled="questionSource === null"
                                       :options="collectionsOptions"
                                       @change="getCollection($event)"
                        />
                        <b-button v-show="questionSource !== null
                                    && !questionChosenFromAssignment()
                                    && questionSource !== 'all_questions'"
                                  size="sm"
                                  variant="primary"
                                  @click="savedQuestionsFoldersType = questionSource;$bvModal.show(`modal-add-saved-questions-folder-${questionSource}`)"
                        >
                          New Folder
                        </b-button>
                      </b-row>
                      <div v-if="questionSource !== 'all_questions'"
                           class="question-bank-scroll"
                           :style="{ maxHeight: questionBankScrollHeight}"
                      >
                        <div v-if="questionChosenFromAssignment()" class="list-group">
                          <div v-if="assignments.length"
                               class="list-group-item all-questions"
                               :style="chosenCourseId === collection ? 'background-color: #EAECEF' : ''"
                          >
                            <a class="hover-underline"
                               @click.prevent="chosenAssignmentId = null;chosenCourseId = collection;getCurrentAssignmentQuestionsBasedOnChosenAssignmentOrSavedQuestionsFolder()"
                            >All questions</a> <span
                            v-show="assignments.filter(assignment => assignment.topics.length).length"
                          >
                              <font-awesome-icon v-if="allTopicsShown"
                                                 :icon="caretDownIcon"
                                                 @click="allTopicsShown=false;showAllTopics(allTopicsShown)"
                              />
                              <font-awesome-icon v-if="!allTopicsShown"
                                                 :icon="caretRightIcon"
                                                 @click="allTopicsShown=true;showAllTopics(allTopicsShown)"
                              />
                            </span>

                            <span class="float-right">{{ all.num_questions }}</span>
                          </div>
                          <div v-for="assignment in assignments" :key="`assignment-${assignment.id}`">
                            <div class="list-group-item"
                                 :style="chosenAssignmentId === assignment.id &&chosenTopicId === null ? 'background-color: #EAECEF' : ''"
                            >
                              <draggable :key="`draggable-key-assignment-${assignment.id}`"
                                         :list="[assignment]"
                                         group="shared"
                              >
                                <span
                                  v-if="!questionChosenFromAssignment() || questionSource === 'my_courses'"
                                  @click="chosenAssignment = assignment;isTopic = true;initFolderAction ('new')"
                                >+</span>
                                <a class="hover-underline assignment"
                                   @click.prevent="chosenCourseId=null;chosenTopicId = null;chosenAssignmentId = assignment.id;getCurrentAssignmentQuestionsBasedOnChosenAssignmentOrSavedQuestionsFolder()"
                                >{{ assignment.name }}</a>
                                <span v-show="assignment.topics.length">
                                  <font-awesome-icon v-if="assignment.topics_shown"
                                                     :icon="caretDownIcon"
                                                     @click="assignment.topics_shown = false;chosenTopicId = null;getCurrentAssignmentQuestionsBasedOnChosenAssignmentOrSavedQuestionsFolder()"
                                  />
                                  <font-awesome-icon v-if="!assignment.topics_shown"
                                                     :icon="caretRightIcon"
                                                     @click="assignment.topics_shown = true"
                                  />
                                </span>
                                <span class="float-right">
                                  {{ assignment.num_questions }}
                                </span>
                              </draggable>
                            </div>
                            <div v-if="assignment.topics.length && assignment.topics_shown">
                              <div v-for="topic in assignment.topics"
                                   :key="`topic-${topic.id}`"
                                   class="list-group-item"
                                   :style="chosenAssignmentId === assignment.id && chosenTopicId === topic.id ? 'background-color: #EAECEF' : ''"
                              >
                                <draggable :key="`draggable-key-topic-${topic.id}`"
                                           :list="[assignment]"
                                           group="shared"
                                >
                                  <span class="ml-5 topic"
                                        :data-assignment-id="`${topic.assignment_id}`"
                                        :data-topic-id="`${topic.id}`"
                                  > <a class="hover-underline"
                                       @click.prevent="chosenCourseId=null;chosenTopicId = topic.id;chosenAssignmentId = assignment.id;getCurrentAssignmentQuestionsBasedOnChosenAssignmentOrSavedQuestionsFolder()"
                                  >{{ topic.name }}</a>
                                    <b-icon icon="pencil"
                                            class="text-muted"
                                            :aria-label="`Edit ${topic.name}`"
                                            @click="chosenTopicAssignmentId=topic.assignment_id;isTopic=true;initFolderAction ('edit', topic)"
                                    />
                                    <b-icon icon="trash"
                                            class="text-muted"
                                            :aria-label="`Delete ${topic.name}`"
                                            @click="chosenTopicAssignmentId=topic.assignment_id;isTopic=true;initFolderAction('delete', topic)"
                                    />
                                  </span>
                                  <span class="float-right">
                                    {{ topic.num_questions }}
                                  </span>
                                </draggable>
                              </div>
                            </div>
                          </div>
                        </div>
                        <ul v-if="!questionChosenFromAssignment()" class="list-group">
                          <li v-if="savedQuestionsFolders"
                              class="list-group-item"
                              :style="allSavedQuestionFoldersByQuestionSource===true ? 'background-color: #EAECEF' : ''"
                          >
                            <a class="hover-underline"
                               @click.prevent="mySavedQuestionsTotalRows = all.num_questions;allSavedQuestionFoldersByQuestionSource = true;getCurrentAssignmentQuestionsBasedOnChosenAssignmentOrSavedQuestionsFolder()"
                            >All questions</a><span class="float-right">{{ all.num_questions }}</span>
                          </li>

                          <li v-for="(currentSavedQuestionsFolder,index) in savedQuestionsFolders"
                              :key="`folder-${currentSavedQuestionsFolder.id}`"
                              class="list-group-item"
                              :style="allSavedQuestionFoldersByQuestionSource===false && chosenAssignmentId === currentSavedQuestionsFolder.id ? 'background-color: #EAECEF' : ''"
                          >
                            <draggable :key="`draggable-key-${index}`"
                                       :list="[currentSavedQuestionsFolder]"
                                       group="shared"
                            >
                              <a
                                :data-folder-id="`${currentSavedQuestionsFolder.id}`"
                                class="hover-underline saved-questions-folder"
                                @click.prevent="mySavedQuestionsTotalRows = currentSavedQuestionsFolder.num_questions;allSavedQuestionFoldersByQuestionSource=false;chosenAssignmentId = currentSavedQuestionsFolder.id;getCurrentAssignmentQuestionsBasedOnChosenAssignmentOrSavedQuestionsFolder()"
                              >{{ currentSavedQuestionsFolder.name }}</a>
                              <a
                                href=""
                                aria-label="Edit Folder"
                                @click.prevent="savedQuestionsFoldersType = questionSource;isTopic=false;initFolderAction('edit', currentSavedQuestionsFolder)"
                              >
                                <b-icon icon="pencil"
                                        class="text-muted"
                                        :aria-label="`Edit ${currentSavedQuestionsFolder.name}`"
                                />
                              </a>
                              <a
                                href=""
                                aria-label="Delete Folder"
                                @click.prevent="isTopic=false;initFolderAction('delete', currentSavedQuestionsFolder)"
                              >
                                <b-icon icon="trash"
                                        class="text-muted"
                                        :aria-label="`Delete ${currentSavedQuestionsFolder.name}`"
                                />
                              </a>
                              <span class="float-right">
                                {{ currentSavedQuestionsFolder.num_questions }}
                              </span>
                            </draggable>
                          </li>
                        </ul>
                      </div>
                    </b-col>
                    <b-col>
                      <b-row class="pb-3">
                        <b-col>
                          <div v-if="questionSource !== 'all_questions'">
                            <b-form-group
                              label="Content"
                              label-for="content-type"
                              label-cols-sm="4"
                              label-align-sm="right"
                              label-size="sm"
                              class="mb-0"
                            >
                              <b-form-select id="content-type"
                                             v-model="content"
                                             :options="contentOptions"
                                             inline
                                             size="sm"
                                             @change="filterByContentType($event)"
                              />
                            </b-form-group>
                          </div>
                          <div v-if="questionSource === 'all_questions' && showPagination">
                            <b-pagination
                              v-model="allQuestionsCurrentPage"
                              :total-rows="allQuestionsTotalRows"
                              :per-page="allQuestionsPerPage"
                              align="center"
                              :limit="withinAssignment ? 7 : 10"
                              first-number
                              last-number
                              class="my-0"
                              @input="getCollection('all_questions')"
                            />
                          </div>
                        </b-col>
                        <b-col v-if="questionSource === 'all_questions'">
                          <b-form-group
                            label="Per page"
                            label-for="all-questions-per-page-select"
                            label-cols-sm="3"
                            label-align-sm="right"
                            label-size="sm"
                            class="mb-0"
                          >
                            <b-form-select
                              id="all-questions-per-page-select"
                              v-model="allQuestionsPerPage"
                              style="width:100px"
                              :options="allQuestionsPageOptions"
                              size="sm"
                              @change="getCollection('all_questions')"
                            />
                          </b-form-group>
                        </b-col>
                        <b-col v-if="questionSource !== 'all_questions'">
                          <b-form-group
                            label-for="filter-input"
                            label-cols-sm="3"
                            label-align-sm="right"
                            label-size="sm"
                            class="mb-0"
                          >
                            <template v-slot:label>
                              Filter
                              <QuestionCircleTooltip :id="'filter-tooltip'"/>
                              <b-tooltip target="filter-tooltip"
                                         delay="250"
                                         triggers="hover focus"
                              >
                                You can filter questions by any text you see in the table. In addition, you can find
                                text
                                based questions or questions
                                converted to text by ADAPT by using the filter.
                              </b-tooltip>
                            </template>
                            <b-input-group v-if="questionSource !== 'my_questions'" size="sm">
                              <b-form-input
                                id="filter-input"
                                v-model="filter"
                                type="search"
                                placeholder="Type to Search"
                                @input="filterResults"
                              />
                              <b-input-group-append>
                                <b-button :disabled="!filter"
                                          @click="clearFilter"
                                >
                                  Clear
                                </b-button>
                              </b-input-group-append>
                            </b-input-group>
                            <b-input-group v-if="questionSource === 'my_questions'" size="sm">
                              <b-form-input
                                id="filter-input"
                                v-model="filter"
                                type="search"
                                placeholder="Type to Search"
                                @search="clearFilter"
                              />
                              <span class="ml-2">
                                <b-button size="sm"
                                          variant="secondary"
                                          @click="filterMySavedQuestions()"
                                >
                                  Submit
                                </b-button>
                              </span>
                            </b-input-group>
                          </b-form-group>
                        </b-col>
                      </b-row>
                      <b-pagination
                        v-if="mySavedQuestionsTotalRows>perPageMinMySavedQuestions && showPagination"
                        v-model="mySavedQuestionsCurrentPage"
                        :total-rows="mySavedQuestionsTotalRows"
                        :per-page="perPageMinMySavedQuestions"
                        align="center"
                        first-number
                        last-number
                        class="pb-3"
                        @input="getCurrentAssignmentQuestionsBasedOnChosenAssignmentOrSavedQuestionsFolder()"
                      />
                      <div v-if="questionSource !== 'all_questions'" class="question-bank-scroll"
                           :style="{ maxHeight: questionBankScrollHeight}"
                      >
                        <table class="table table-striped" style="position: sticky;top: 0">
                          <thead>
                          <tr>
                            <th scope="col" class="pb-3 header" style="width:150px">
                              ID
                            </th>
                            <th scope="col" class="header" style="min-width: 300px !important">
                              <div class="t-2">
                                <input :class="`select_all-${questionSource}`" type="checkbox"
                                       @click="numViewSelectedQuestionsClicked++;selectAll()"
                                >
                                Title
                                <span class="ml-3"><b-form-select :id="`selected-${questionSource}`"
                                                                  v-model="bulkAction"
                                                                  inline
                                                                  :disabled="!selectedQuestionIds.length"
                                                                  :options="getBulkActions(questionSource)"
                                                                  style="width:200px"
                                                                  size="sm"
                                                                  @change="actOnBulkAction($event)"
                                />
                                  </span>
                                <br> <input v-model="showDescriptions"
                                            type="checkbox"
                                            @click="updateShowDescriptions"
                              >
                                Show Descriptions
                              </div>
                            </th>
                            <th v-if="questionChosenFromAssignment() && chosenAssignmentId && !chosenTopicId"
                                scope="col" class="pb-3 header"
                            >
                              Topic
                            </th>
                            <th v-if="questionSource === 'all_questions'" scope="col" class="pb-3 header wrapWord">
                              Tags
                            </th>
                            <th v-if="questionSource === 'my_questions'" scope="col" class="pb-3 header">
                              Public
                            </th>
                            <th scope="col" class="pb-3 header">
                              Actions
                            </th>
                          </tr>
                          </thead>
                          <draggable
                            :list="assignmentQuestions"
                            group="shared"
                            tag="tbody"
                            :draggable="'.can-drag'"
                            @end="questionChosenFromAssignment() && questionSource === 'my_courses' ? moveToNewTopic($event) : moveToNewFolder($event)"
                            @start="setQuestionId"
                          >
                            <tr v-for="(assignmentQuestion, index) in assignmentQuestions"
                                :key="`assignmentQuestion-${index}`"
                                :data-question-id-to-move="assignmentQuestion.question_id"
                                :class="{'can-drag': !questionChosenFromAssignment() || (questionChosenFromAssignment() && questionSource === 'my_courses')}"
                            >
                              <td>
                                <span><font-awesome-icon
                                  v-if="!questionChosenFromAssignment() || questionSource === 'my_courses'"
                                  :icon="barsIcon"
                                />
                                  <span :id="`question_id-${assignmentQuestion.question_id}`"
                                  >{{ assignmentQuestion.question_id }}</span>
                                  <a href=""
                                     aria-label="Copy Question ID"
                                     @click.prevent="doCopy(`question_id-${assignmentQuestion.question_id}`,'The question ID has been copied to your clipboard.')"
                                  >
                                    <font-awesome-icon :icon="copyIcon" class="text-muted"/>
                                  </a>
                                </span>
                              </td>
                              <td style="min-width: 300px !important">
                                <input v-model="selectedQuestionIds" type="checkbox"
                                       :value="assignmentQuestion.question_id"
                                       :class="`selected-question-id-${questionSource}`"
                                >
                                <GetQuestionsTitle :assignment-question="assignmentQuestion"
                                                   @initViewSingleQuestion="initViewSingleQuestion"
                                />
                                <div v-if="showDescriptions && assignmentQuestion.description">
                                  <span class="small">{{ assignmentQuestion.description }}</span>
                                </div>
                              </td>
                              <td v-if="questionChosenFromAssignment() && chosenAssignmentId && !chosenTopicId">
                                {{ assignmentQuestion.topic }}
                              </td>
                              <td v-if="questionSource === 'all_questions'" class="wrapWord">
                                {{ assignmentQuestion.tags }}
                              </td>
                              <td v-if="questionSource === 'my_questions'">
                                {{ assignmentQuestion.public ? 'Yes' : 'No' }}
                              </td>
                              <td style="width:150px">
                                <GetQuestionsActions :assignment-question="assignmentQuestion"
                                                     :heart-icon="heartIcon"
                                                     :question-source="questionSource"
                                                     :within-assignment="withinAssignment"
                                                     :assignment-questions="assignmentQuestions"
                                                     @removeMyFavoritesQuestion="removeMyFavoritesQuestion"
                                                     @initSaveToMyFavorites="initSaveToMyFavorites"
                                                     @initRemoveAssignmentQuestion="initRemoveAssignmentQuestion"
                                                     @addQuestions="addQuestions"
                                                     @reloadCurrentAssignmentQuestions="getCurrentAssignmentQuestionsBasedOnChosenAssignmentOrSavedQuestionsFolder"
                                />
                              </td>
                            </tr>
                          </draggable>
                        </table>
                        <div v-if="processingGetCollection" class="text-center mt-5">
                          <b-spinner small type="grow"/>
                          <span style="font-size:20px;">Loading</span>
                        </div>
                        <div v-if="!processingGetCollection">
                          <div v-if="questionChosenFromAssignment()">
                            <b-alert :show="!assignmentQuestions.length && collection !== null" variant="info">
                              <span class="font-weight-bold">
                                There are no questions for this selection.
                              </span>
                            </b-alert>
                          </div>
                          <div v-if="!questionChosenFromAssignment()">
                            <b-alert :show="!assignmentQuestions.length" variant="info">
                              <span class="font-weight-bold">
                                This folder has no questions.
                              </span>
                            </b-alert>
                          </div>
                        </div>
                      </div>
                    </b-col>
                  </b-row>
                </b-container>
                <b-form v-if="questionSource === 'all_questions'">
                  <div v-if="user.role === 5">
                    <b-form-group
                      label="Course"
                      label-for="course"
                      label-cols-sm="1"
                      label-align-sm="right"
                      label-size="sm"
                    >
                      <b-form-select id="course"
                                     v-model="commonsCourse"
                                     :options="commonsCourseOptions"
                                     inline
                                     style="width:400px"
                                     size="sm"
                                     @change="updateCommonsCourseAssignmentOptions($event)"
                      />
                    </b-form-group>
                    <b-form-group
                      label="Assignment"
                      label-for="assignment"
                      label-cols-sm="1"
                      label-align-sm="right"
                      label-size="sm"
                    >
                      <b-form-select id="assignment"
                                     v-model="commonsCourseAssignment"
                                     :options="commonsCourseAssignmentOptions"
                                     inline
                                     style="width:400px"
                                     size="sm"
                      />
                    </b-form-group>
                  </div>
                  <div v-if="user.role !== 5">
                    <b-form inline class="pb-3">
                      <label for="class" style="font-size:15px;margin-left:45px;margin-right:10px;text-align:right">Class</label>
                      <b-form-select id="class"
                                     v-model="allQuestionsClass"
                                     :options="classOptions"
                                     inline
                                     style="width:175px"
                                     size="sm"
                                     @change="filterByContentType($event)"
                      />
                    </b-form>
                    <b-form inline class="pb-3">
                      <label style="margin-left:33px;font-size:14px;margin-right:8px">Content</label>
                      <b-form-select id="content-type"
                                     v-model="allQuestionsContent"
                                     :options="contentOptions"
                                     inline
                                     style="width:175px"
                                     size="sm"
                                     @change="filterByContentType($event)"
                      />
                    </b-form>
                    <b-form inline class="pb-3" v-if="allQuestionsContent !== 'open_ended_only'">
                      <label class="ml-2" style="font-size:14px;margin-right:10px">Technology</label>
                      <b-form-select
                        id="all-questions-technology-select"
                        v-model="allQuestionsTechnology"
                        style="width:175px"
                        :options="allQuestionsTechnologyOptions"
                        size="sm"
                        @change="changeTechnology"
                      />
                      <b-form-radio-group
                        v-if="allQuestionsTechnology === 'qti'"
                        id="qti-question-type"
                        v-model="qtiContentType"
                        inline
                        name="qti-question-type"
                        class="pl-2"
                        @change="formattedQuestionType = null"
                      >
                        <b-form-radio value="basic">
                          Basic
                        </b-form-radio>
                        <b-form-radio value="nursing">
                          Nursing
                          <QuestionCircleTooltip id="nursing-questions-tooltip" class="pl-1"/>
                          <b-tooltip target="nursing-questions-tooltip"
                                     delay="250"
                                     triggers="hover focus"
                          >
                            Nursing questions are question types specifically written to prepare nursing students for
                            the NCLEX
                            exam.
                          </b-tooltip>
                        </b-form-radio>
                        <b-form-radio value="sketcher">
                          Sketcher
                        </b-form-radio>
                        <b-form-radio value="discuss_it" @click="setFormattedQuestionType('discuss_it')">
                          Discuss-it
                        </b-form-radio>
                        <b-form-radio value="all">
                          All
                        </b-form-radio>
                      </b-form-radio-group>
                      <label v-if="['h5p','imathas'].includes(allQuestionsTechnology)" class="ml-4"
                             style="font-size:14px;margin-right:11px"
                      >Technology ID</label>
                      <b-form-input v-if="['h5p','imathas'].includes(allQuestionsTechnology)"
                                    id="all-questions-technology-id"
                                    v-model="allQuestionsTechnologyId"
                                    style="height:31px;width: 75px"
                      />
                      <span v-if="allQuestionsTechnology === 'webwork'">
                        <b-form-radio-group
                          id="webwork-content-type"
                          v-model="webworkContentType"
                          inline
                          name="webwork-file-type"
                          class="pl-2 mt-1"
                        >
                          <span
                            style="font-size:14px;margin-right:11px;"
                            class="mt-2"
                          >Technology ID</span>
                          <b-form-radio value="pgml">
                            pgml
                          </b-form-radio>
                          <b-form-radio value="pl">
                            pl
                          </b-form-radio>
                          <b-form-radio value="either">
                            Either
                          </b-form-radio>
                        </b-form-radio-group>
                        <b-form-radio-group
                          id="wwebwork-algorithmic"
                          v-model="webworkAlgorithmic"
                          inline
                          name="webwork-algorithmic"
                          class="pl-2 mt-1"
                        >
                          <span
                            style="font-size:14px;margin-right:29px;"
                            class="mt-2"
                          >Algorithmic</span>
                          <b-form-radio value="algorithmic only">
                            Algorithmic Only
                          </b-form-radio>
                          <b-form-radio value="non-algorithmic only">
                            Non-algorithmic only
                          </b-form-radio>
                          <b-form-radio value="either">
                            Either
                          </b-form-radio>
                        </b-form-radio-group>
                      </span>
                    </b-form>
                  </div>
                  <b-form-group
                    v-show="['basic','nursing','all','sketcher'].includes(qtiContentType)"
                    label-for="adapt-id"
                    label-cols-sm="1"
                    label-align-sm="right"
                    label-size="sm"
                    label="Type"
                  >
                    <span style="font-size:14px" :class="!formattedQuestionType ? 'text-muted' : ''">{{
                        formattedQuestionType ? formattedQuestionType : 'None Chosen'
                      }}</span>
                    <span class="ml-2">
                      <b-button variant="outline-info" size="sm" @click="initChooseType()">
                        Choose Type
                      </b-button>
                      <b-button variant="outline-secondary" size="sm" @click="formattedQuestionType = null">
                        Reset Type
                      </b-button>
                    </span>
                  </b-form-group>
                  <div v-if="questionSubjectIdOptions.length>1">
                    <b-form-group
                      label-for="subject"
                      label-cols-sm="2"
                      label-cols-lg="1"
                      label="Subject"
                    >
                      <b-form-select v-model="questionSubjectId"
                                     :options="questionSubjectIdOptions"
                                     size="sm"
                                     style="width:400px"
                                     @change="questionChapterId = null;questionSectionId = null;getQuestionChapterIdOptions(questionSubjectId)"
                      />
                    </b-form-group>
                    <b-form-group
                      label-for="chapter"
                      label-cols-sm="2"
                      label-cols-lg="1"
                      label="Chapter"
                    >
                      <b-form-select v-model="questionChapterId"
                                     :options="questionChapterIdOptions"
                                     size="sm"
                                     style="width:400px"
                                     :disabled="questionSubjectId === null"
                                     @change="questionSectionId = null;getQuestionSectionIdOptions(questionChapterId)"
                      />
                    </b-form-group>
                    <b-form-group
                      label-for="section"
                      label-cols-sm="2"
                      label-cols-lg="1"
                      label="Section"
                    >
                      <b-form-select v-model="questionSectionId"
                                     :options="questionSectionIdOptions"
                                     size="sm"
                                     style="width:400px"
                                     :disabled="questionChapterId === null || questionSectionIdOptions.length === 1"
                      />
                    </b-form-group>
                  </div>

                  <b-form-group
                    label-for="adapt-id"
                    label-cols-sm="1"
                    label-align-sm="right"
                    label-size="sm"
                  >
                    <template v-slot:label>
                      ADAPT ID
                      <QuestionCircleTooltip :id="'adapt-id'"/>
                    </template>
                    <b-tooltip target="adapt-id"
                               delay="250"
                               triggers="hover focus"
                    >
                      Search for a specific question using its ADAPT ID. If this field is filled out, all other fields
                      will be ignored.
                    </b-tooltip>
                    <b-input-group size="sm" style="width:175px">
                      <b-form-input
                        id="adapt-id"
                        v-model="allQuestionsAdaptId"
                      />
                    </b-input-group>
                  </b-form-group>

                  <b-form-group
                    label-for="all-questions-title"
                    label-cols-sm="1"
                    label-align-sm="right"
                    label-size="sm"
                  >
                    <template v-slot:label>
                      Title
                      <QuestionCircleTooltip :id="'title-tooltip'"/>
                    </template>
                    <b-tooltip target="title-tooltip"
                               delay="250"
                               triggers="hover focus"
                    >
                      Full or partial text contained in the title
                    </b-tooltip>
                    <b-input-group size="sm" style="width:400px">
                      <b-form-input
                        id="all-questions-author"
                        v-model="allQuestionsTitle"
                      />
                    </b-input-group>
                  </b-form-group>
                  <div v-if="user.role !== 5">
                    <b-form-group
                      label-for="all-questions-author"
                      label-cols-sm="1"
                      label-align-sm="right"
                      label-size="sm"
                      label="Author"
                    >
                      <b-input-group size="sm" style="width:400px">
                        <b-form-input
                          id="all-questions-author"
                          v-model="allQuestionsAuthor"
                        />
                      </b-input-group>
                    </b-form-group>
                    <b-form-group
                      label-for="all-questions-tags"
                      label-cols-sm="1"
                      label-align-sm="right"
                      label-size="sm"
                      label="Tag(s)"
                    >
                      <template v-slot:label>
                        Tag(s)
                        <QuestionCircleTooltip :id="'tags-tooltip'"/>
                      </template>
                      <b-tooltip target="tags-tooltip"
                                 delay="250"
                                 triggers="hover focus"
                      >
                        Comma separated list. Partial words are OK (for example, the tag deriv will return questions
                        with
                        the tag derivative as well).
                        For WeBWork questions, if the tag appears in the file path, these questions will be returned as
                        well.
                      </b-tooltip>

                      <b-input-group size="sm" style="width:400px">
                        <b-form-input
                          id="all-questions-tags"
                          v-model="allQuestionsTags"
                        />
                      </b-input-group>
                    </b-form-group>
                  </div>
                  <div style="margin-left:100px" class="mb-4">
                    <b-button variant="primary" size="sm" @click="getCollection('all_questions')">
                      Update Results
                    </b-button>
                    <span class="font-weight-bold ml-5">
  {{ Number(allQuestionsTotalRows).toLocaleString() }} {{
                        Number(allQuestionsTotalRows) === 1 ? 'question' : 'questions'
                      }}
</span>
                  </div>
                </b-form>
                <div v-show="false" id="adapt-ids">
                  <span v-html="selectedQuestionIds.join(', ')"/>
                </div>
                <div v-if="questionSource === 'all_questions'">
                  <b-table
                    id="all_questions"
                    ref="allQuestionsTable"
                    aria-label="Search Questions"
                    style="font-size:14px"
                    striped
                    hover
                    :sticky-header="questionBankScrollHeight"
                    :no-border-collapse="true"
                    :current-page="allQuestionsCurrentPage"
                    :items="assignmentQuestions"
                    :fields="allQuestionsFields"
                  >
                    <template v-slot:head(question_id)>
                      ADAPT ID
                    </template>
                    <template v-slot:head(title)>
                      <input :class="`select_all-${questionSource}`" type="checkbox"
                             style="margin-top:15px"
                             @click="numViewSelectedQuestionsClicked++;selectAll()"
                      > Title
                      <span class="ml-3"><b-form-select :id="`selected-${questionSource}`"
                                                        v-model="bulkAction"
                                                        inline
                                                        :disabled="!selectedQuestionIds.length"
                                                        :options="getBulkActions('all_questions')"
                                                        style="width:165px"
                                                        size="sm"
                                                        @change="actOnBulkAction($event)"
                      /></span>
                      <br> <input v-model="showDescriptions"
                                  type="checkbox"
                                  @click="updateShowDescriptions"
                    >
                      Show Descriptions
                    </template>
                    <template v-slot:cell(title)="data">
                      <input v-model="selectedQuestionIds" type="checkbox"
                             :value="data.item.question_id"
                             :class="`selected-question-id-${questionSource}`"
                      >
                      <GetQuestionsTitle :assignment-question="data.item"
                                         @initViewSingleQuestion="initViewSingleQuestion"
                      />
                      <FormativeWarning :formative-question="data.item.in_formative_assignment"
                                        :question-id="data.item.id"
                                        :small="true"
                      />

                      <div v-if="showDescriptions && data.item.description">
                        <span class="small">{{ data.item.description }}</span>
                      </div>
                    </template>
                    <template v-slot:cell(question_id)="data">
                      <span :id="`question_id-${data.item.question_id}`">{{ data.item.question_id }}</span>
                      <a href=""
                         aria-label="Copy Question ID"
                         @click.prevent="doCopy(`question_id-${data.item.question_id}`,'The question ID has been copied to your clipboard.')"
                      >
                        <font-awesome-icon :icon="copyIcon" class="text-muted"/>
                      </a>
                    </template>
                    <template v-slot:cell(technology)="data">
                      {{
                        allQuestionsTechnologyOptions.find(item => item.value === data.item.technology) ? allQuestionsTechnologyOptions.find(item => item.value === data.item.technology).text : 'None'
                      }}
                    </template>
                    <template v-slot:cell(tag)="data">
                      <span v-html="data.item.tag"/>
                    </template>
                    <template v-slot:cell(type)="data">
                      <div style="white-space: nowrap;">
                        {{ data.item.question_type ? data.item.question_type : 'Unknown' }}
                        <span v-show="interactiveH5PFormattedTypes.includes(data.item.question_type)">
                          <QuestionCircleTooltip :id="`h5p-interactive-tooltip-${data.item.id}`"
                                                 :color="'text-danger'"
                          />
                          <b-tooltip :target="`h5p-interactive-tooltip-${data.item.id}`"
                                     delay="250"
                                     triggers="hover focus"
                          >
                            This question is an interactive question and has no auto-graded component associated with it.  It acts like an ADAPT open-ended question.
                          </b-tooltip>
                        </span>
                      </div>
                    </template>
                    <template v-slot:cell(action)="data">
                      <GetQuestionsActions :assignment-question="data.item"
                                           :heart-icon="heartIcon"
                                           :assignment-questions="assignmentQuestions"
                                           :question-source="questionSource"
                                           :within-assignment="withinAssignment"
                                           @removeMyFavoritesQuestion="removeMyFavoritesQuestion"
                                           @initSaveToMyFavorites="initSaveToMyFavorites"
                                           @initRemoveAssignmentQuestion="initRemoveAssignmentQuestion"
                                           @addQuestions="addQuestions"
                                           @reloadAllQuestions="reloadAllQuestions"
                      />
                    </template>
                  </b-table>
                </div>
              </b-tab>
              <b-tab v-if="withinAssignment" title="Direct Import By ID" class="pb-8"
                     @click="resetDirectImportMessages();showQuestions = false"
              >
                <b-card header-html="<h2 class='h7'>Direct Import By ID</h2>" style="height:425px">
                  <b-card-text>
                    <b-container>
                      <b-row>
                        <b-col @click="resetSearchByTag">
                          <p>
                            Perform a direct import of questions into your assignment either using the ADAPT ID
                            or the Question ID.
                          </p>
                          <p>
                            ADAPT IDs can be found in the Questions tab of any assignment and are of the form
                            {Assignment
                            ID}-{Question ID}.
                            Question IDs can be copied directly from the
                            <span><router-link :to="{path: '/all-questions/get'}" target="_blank">
                              Search Questions</router-link> page.</span>
                          </p>
                          <p>
                            Please enter the IDs in a comma separated list.
                          </p>
                          <b-row>
                            <b-alert v-if="isFormative" show variant="info">
                              Please note that since this is a formative assignment, you can only add questions that you
                              own.
                            </b-alert>
                          </b-row>
                        </b-col>
                        <b-col>
                          <b-form-textarea
                            v-model="directImport"
                            aria-label="ADAPT IDs to direct import"
                            placeholder="Example. 1027-34, 1029-38, 1051-44, 111130"
                            rows="4"
                            max-rows="5"
                          />
                          <div class="float-right mt-2">
                            <span v-if="directImportingQuestions" class="mr-3">
                              Processing {{ parseInt(directImportIndex) + 1 }} of {{ directImportCount }}
                            </span>
                            <b-button variant="success" size="sm" class="mr-2"
                                      @click="directImportQuestions('adapt id')"
                            >
                              <b-spinner v-if="directImportingQuestions" small type="grow"/>
                              Import Questions
                            </b-button>
                          </div>
                        </b-col>
                      </b-row>
                      <div class="mb-4">
                        <div v-if="errorDirectImportIdsMessage.length>0">
                          <b-alert :show="true" variant="danger">
                            <span class="font-weight-bold">{{ errorDirectImportIdsMessage }}</span>
                          </b-alert>
                        </div>
                        <div v-if="directImportIdsAddedToAssignmentMessage.length>0">
                          <b-alert :show="true" variant="success">
                            <span class="font-weight-bold">{{ directImportIdsAddedToAssignmentMessage }}</span>
                          </b-alert>
                        </div>
                        <div v-if="directImportIdsNotAddedToAssignmentMessage.length>0">
                          <b-alert :show="true" variant="info">
                            <span class="font-weight-bold">{{ directImportIdsNotAddedToAssignmentMessage }}</span>
                          </b-alert>
                        </div>
                      </div>
                    </b-container>
                  </b-card-text>
                </b-card>
              </b-tab>
            </b-tabs>
          </div>
        </div>

        <div v-if="questions.length>0 && showQuestions" class="overflow-auto">
          <b-pagination
            v-model="currentPage"
            :total-rows="questions.length"
            :per-page="perPage"
            align="center"
            first-number
            last-number
            @input="changePage(currentPage)"
          />
        </div>
        <div v-if="showQuestions">
          <b-container>
            <b-row v-if="questions[currentPage-1]">
              <span v-if="!questions[currentPage-1].inAssignment">
                <b-button class="mt-1 mb-2 mr-2"
                          variant="primary"
                          size="sm"
                          @click="addQuestion(questions[currentPage-1])"
                >Add Question
                </b-button>
              </span>
              <span v-if="questions[currentPage-1].inAssignment">
                <b-button class="mt-1 mb-2 mr-2"
                          variant="danger"
                          size="sm"
                          @click="isRemixerTab = false; questionToRemove = questions[currentPage-1];openRemoveQuestionModal()"
                >Remove Question
                </b-button>
              </span>
            </b-row>
          </b-container>
          <div>
            <iframe v-if="showQuestions && questions[currentPage-1] && questions[currentPage-1].non_technology"
                    id="non-technology-iframe"
                    allowtransparency="true"
                    frameborder="0"
                    :src="questions[currentPage-1].non_technology_iframe_src"
                    style="width: 1px;min-width: 100%;"
            />
          </div>
          <div v-if="questions[currentPage-1] && questions[currentPage-1].technology_iframe">
            <iframe
              :key="`technology-iframe-${questions[currentPage-1].id}`"
              v-resize="{ log: true, checkOrigin: false }"
              width="100%"
              :src="questions[currentPage-1].technology_iframe"
              frameborder="0"
            />
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import axios from 'axios'
import { h5pResizer } from '~/helpers/H5PResizer'
import { mapGetters } from 'vuex'
import { submitUploadFile, getAcceptedFileTypes } from '~/helpers/UploadFiles'
import { getTechnologySrc, getQuestionChapterIdOptions, getQuestionSectionIdOptions } from '~/helpers/Questions'
import { downloadSolutionFile } from '~/helpers/DownloadFiles'
import Form from 'vform'
import Loading from 'vue-loading-overlay'
import 'vue-loading-overlay/dist/vue-loading.css'
import libraries from '~/helpers/Libraries'
import AssessmentTypeWarnings from '~/components/AssessmentTypeWarnings'
import ViewQuestions from '~/components/ViewQuestions'
import SolutionFileHtml from '~/components/SolutionFileHtml'
import $ from 'jquery'

import {
  h5pText,
  updateLearningTreeInNonLearningTreeMessage,
  updateNonLearningTreeInLearningTreeMessage
} from '~/helpers/AssessmentTypeWarnings'

import RemoveQuestion from '~/components/RemoveQuestion'
import { faHeart, faCopy } from '@fortawesome/free-regular-svg-icons'
import { faBars, faCaretDown, faCaretRight, faExternalLinkAlt } from '@fortawesome/free-solid-svg-icons'
import { getTooltipTarget, initTooltips } from '~/helpers/Tooptips'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import SavedQuestionsFolders from '~/components/SavedQuestionsFolders'
import _ from 'lodash'
import draggable from 'vuedraggable'
import GetQuestionsActions from '~/components/GetQuestionsActions'
import GetQuestionsTitle from '~/components/GetQuestionsTitle'
import { doCopy } from '~/helpers/Copy'
import QtiJsonAnswerViewer from '../QtiJsonAnswerViewer'
import FormativeWarning from '../FormativeWarning.vue'
import { initCentrifuge } from '~/helpers/Centrifuge'
import FormattedQuestionTypeRadio from '../FormattedQuestionTypeRadio.vue'
import { isQtiOrForgeWithQtiAnswerSolution } from '../../helpers/Questions'

export default {
  components: {
    FormattedQuestionTypeRadio,
    FormativeWarning,
    QtiJsonAnswerViewer,
    GetQuestionsTitle,
    GetQuestionsActions,
    SavedQuestionsFolders,
    SolutionFileHtml,
    AssessmentTypeWarnings,
    Loading,
    RemoveQuestion,
    FontAwesomeIcon,
    ViewQuestions,
    draggable
  },
  middleware: 'auth',
  props: {
    parentAssignmentId: {
      type: Number,
      default: 0
    },
    parentQuestionSource: {
      type: String,
      default: ''
    },
    withH5p: {
      type: Number,
      default: 0
    }
  },
  data: () => ({
    questionSubjectId: null,
    questionSubjectIdOptions: [],
    questionChapterId: null,
    questionChapterIdOptions: [],
    questionSectionId: null,
    questionSectionIdOptions: [],
    checkedForDiscussItQuestions: false,
    resetDiscussItSettingsToDefault: '1',
    questionsToAdd: [],
    interactiveH5PFormattedTypes: [
      'Accordion',
      'Advent Calendar (beta)',
      'AR Scavenger (beta)',
      'Branching Scenario',
      'Cornell Notes',
      'Image Juxtaposition',
      'Image Zoom',
      'KewAr Code',
      'None Provided',
      'Questionnaire',
      'Speak the Words',
      'Advent Calendar (beta) Test',
      'Arithmetic Quiz',
      'Chart',
      'Course Presentation',
      'Documentation Tool',
      'Flashcards',
      'Iframe Embedder',
      'Information Wall',
      'Structure Strip',
      'Twitter User Feed',
      'Agamotto',
      'Audio',
      'Collage',
      'Memory Game',
      'Personality Quiz',
      'Virtual Tour (360)',
      'Appear.in for Chat and Talk',
      'Audio Recorder',
      'Dialog Cards',
      'Guess the Answer',
      'Image Hotspots',
      'Image Slider',
      'Timeline'
    ],
    qtiContentType: '',
    formattedQuestionTypesOptionsByTechnology: [],
    formattedQuestionType: null,
    columnSize: 1,
    formattedQuestionTypesOptions: [],
    showDescriptions: false,
    centrifuge: {},
    webworkAlgorithmic: 'either',
    webworkContentType: 'either',
    isFormative: false,
    qtiQuestionType: 'basic',
    allQuestionsAdaptId: '',
    questionSourceKey: 0,
    commonsCourseAssignments: [],
    commonsCourse: null,
    commonsCourseAssignment: null,
    commonsCourseOptions: [],
    commonsCourseAssignmentOptions: [],
    showPagination: true,
    perPageMinMySavedQuestions: 200,
    mySavedQuestionsTotalRows: 1,
    mySavedQuestionsCurrentPage: 1,
    technologySrc: '',
    withinAssignment: true,
    externalLinkIcon: faExternalLinkAlt,
    allQuestionsFields: [
      {
        key: 'question_id',
        label: 'ADAPT ID',
        isRowHeader: true,
        thStyle: 'width:100px'
      },
      { key: 'title', thStyle: { minWidth: '300px !important' }, tdStyle: { minWidth: '300px !important' } },
      'author',
      'technology',
      'type',
      {
        key: 'tag', label: 'Tags', tdClass: 'wrapWord'
      },
      {
        key: 'action',
        thStyle: 'width:90px'
      }
    ],
    allQuestionsTags: '',
    allQuestionsTechnologyId: '',
    allQuestionsAuthor: '',
    allQuestionsTitle: '',
    allQuestionsContent: 'both',
    allQuestionsTechnology: 'any',
    allQuestionsTechnologyOptions: [
      { value: 'any', text: 'Any technology' },
      { value: 'qti', text: 'Native' },
      { value: 'h5p', text: 'H5P' },
      { value: 'forge', text: 'Forge' },
      { value: 'imathas', text: 'IMathAS' },
      { value: 'webwork', text: 'WeBWork' }
    ],
    allQuestionsPageOptions: [10, 50, 100, 500, 1000],
    allQuestionsPerPage: 100,
    allQuestionsTotalRows: 0,
    allQuestionsCurrentPage: 1,
    filteredTopicsOptions: [],
    allTopicsShown: false,
    chosenTopicAssignmentId: 0,
    topicsOptions: [],
    topicToMoveQuestionsTo: null,
    chosenTopicId: null,
    chosenAssignment: {},
    isTopic: false,
    folderLevel: 1,
    isPointsByQuestionWeightAndSubmissionsExist: false,
    saveToMyFavoritesKey: 0,
    savedQuestionsFoldersType: 'my_favorites',
    processingGetCollection: false,
    questionIdToMove: 0,
    all: {},
    allSavedQuestionFoldersByQuestionSource: false,
    chosenCourseId: null,
    moveOrRemoveQuestionsMyFavoritesKey: 0,
    updatedDraggable: 0,
    chosenAssignmentIndex: null,
    originalAssignmentQuestions: [],
    questionToRemoveFromFavoritesFolder: {},
    saveToMyFavoritesQuestionIds: [],
    bulkAction: 'null',
    bulkActionOptions: [
      { value: null, text: 'Choose Bulk Action' },
      { value: 'view', text: 'View Questions' },
      { value: 'copy_adapt_ids', text: 'Copy ID\'s' },
      { value: 'add_to_assignment', text: 'Add To Assignment' },
      { value: 'bulk_move_to_new_topic', text: 'Move To Topic' }
    ],
    allQuestionsClass: 'any',
    classOptions: [
      {
        value: 'any', text: 'Any class'
      },
      {
        value: 'assessment', text: 'Question'
      },
      {
        value: 'exposition', text: 'Exposition'
      }],
    content: 'both',
    contentOptions: [
      {
        value: 'both', text: 'Either content type'
      },
      {
        value: 'auto_graded_only', text: 'Auto-graded, only'
      },
      {
        value: 'open_ended_only', text: 'Open-ended, only'
      }],
    filter: '',
    questionSource: null,
    questionSourceOptions: [{ value: null, text: 'Please choose a question source' },
      { value: 'all_questions', text: 'Search Questions' },
      { value: 'my_questions', text: 'My Questions' },
      { value: 'my_favorites', text: 'My Favorites' },
      { value: 'my_courses', text: 'My Courses' },
      { value: 'commons', text: 'Commons' },
      { value: 'all_public_courses', text: 'All Public Courses' }
    ],
    folderAction: null,
    savedQuestionsFolders: [],
    savedQuestionsFolder: null,
    questionBankScrollHeight: '0px',
    questionBankModalShown: false,
    questionToView: {},
    numViewSelectedQuestionsClicked: 0,
    assignmentQuestionsKey: 0,
    heartIcon: faHeart,
    copyIcon: faCopy,
    caretDownIcon: faCaretDown,
    caretRightIcon: faCaretRight,
    barsIcon: faBars,
    selectedQuestionIds: [],
    assignmentQuestions: [],
    collection: null,
    collectionsOptions: [{ value: null, text: `Please choose a collection` }],
    assignments: [],
    questionToMove: {},
    chosenAssignmentId: 0,
    assignmentName: '',
    assignmentId: 0,
    remixerKey: 0,
    modalRemoveQuestionKey: 0,
    h5pQuestionsWithAnonymousUsers: false,
    assessmentTypeWarningsKey: 0,
    betaAssignmentsExist: false,
    questionToRemove: {},
    isRemixerTab: true,
    errorDirectImportIdsMessage: '',
    directImportCount: '',
    directImportIndex: '',
    openEndedQuestionsInRealTime: '',
    learningTreeQuestionsInNonLearningTree: '',
    nonLearningTreeQuestions: '',
    showQuestion: false,
    school: '',
    schools: [],
    assessmentType: '',
    loadingQuestion: false,
    defaultImportLibrary: null,
    libraryOptions: libraries,
    directImportIdsNotAddedToAssignmentMessage: '',
    directImportIdsAddedToAssignmentMessage: '',
    directImportingQuestions: false,
    directImport: '',
    questionFilesAllowed: false,
    uploading: false,
    continueLoading: true,
    isLoading: true,
    iframeLoaded: false,
    perPage: 1,
    currentPage: 1,
    query: '',
    tags: [],
    questions: [],
    chosenTags: [],
    question: {},
    showQuestions: false,
    title: '',
    uploadFileForm: new Form({
      questionFile: null,
      assignmentId: null,
      questionId: null
    })
  }),
  computed: {
    ...mapGetters({
      user: 'auth/user'
    }),
    isAdmin: () => window.config.isAdmin
  },
  created () {
    this.doCopy = doCopy
    this.getTechnologySrc = getTechnologySrc
    this.submitUploadFile = submitUploadFile
    this.getAcceptedFileTypes = getAcceptedFileTypes
    this.downloadSolutionFile = downloadSolutionFile
    this.updateLearningTreeInNonLearningTreeMessage = updateLearningTreeInNonLearningTreeMessage
    this.updateNonLearningTreeInLearningTreeMessage = updateNonLearningTreeInLearningTreeMessage
    this.h5pText = h5pText
  },
  beforeDestroy () {
    try {
      if (this.centrifuge) {
        this.centrifuge.disconnect()
        console.log(this.centrifuge)
      }
    } catch (error) {
      // won't be a function for all the other ones that haven't been defined on the page
    }
  },
  async mounted () {
    if (![2, 5].includes(this.user.role)) {
      await this.$router.push({ name: 'no.access' })
      return false
    }
    if (this.user.role === 5) {
      await this.getCommonsCoursesAndAssignments()
    }
    this.getTooltipTarget = getTooltipTarget
    initTooltips(this)
    await this.getFormattedQuestionTypes()
    for (let i = 1; i < this.libraryOptions.length; i++) {
      let library = this.libraryOptions[i]
      this.libraryOptions[i].text = `${library.text} (${library.value})`
    }
    await this.getDefaultImportLibrary()
    if (this.parentAssignmentId) {
      this.assignmentId = this.parentAssignmentId
      await this.getAssignmentInfo()
      await this.getQuestionWarningInfo()
      this.isLoading = false
    } else if (this.parentQuestionSource) {
      this.$nextTick(() => {
        $('.questions-get-tabs').find('ul[role="tablist"]').remove()
      })
      this.questionSource = this.parentQuestionSource
      this.withinAssignment = false
      this.title = this.questionSource === 'all_questions' ? 'Search Questions' : _.startCase(this.questionSource.replace('_', ' '))
      await this.getCollection(this.questionSource)
    } else {
      this.$noty.error('This component needs either an assignment ID or a parentQuestionSource')
      this.isLoading = false
      return false
    }
    this.fixQuestionBankScrollHeight()
    if (this.isFormative) {
      this.questionSource = 'my_questions'
      await this.getCollection('')
    }
    await this.getShowDescriptions()
    await this.getSubjectsByTechnology()
  },
  methods: {
    isQtiOrForgeWithQtiAnswerSolution,
    doCopy,
    initCentrifuge,
    getQuestionChapterIdOptions,
    getQuestionSectionIdOptions,
    changeTechnology () {
      this.questionSubjectId = null
      this.questionChapterId = null
      this.questionSectionId = null
      this.questionChapterIdOptions = []
      this.questionSubjectIdOptions = []
      this.questionSectionIdOptions = []
      this.webworkContentType = 'either'
      if (this.allQuestionsTechnology === 'text') {
        this.formattedQuestionType = null
      }
      this.getSubjectsByTechnology()
    },
    async getSubjectsByTechnology () {
      this.questionChapterId = null
      this.questionSectionId = null
      const { data } = await axios.get(`/api/question-subjects/technology/${this.allQuestionsTechnology}`)
      if (data.type === 'error') {
        this.$noty.error(data.message)
      }
      if (data.question_subjects) {
        this.questionChapterIdOptions = [{ value: null, text: 'Choose a chapter' }]
        this.questionSubjectIdOptions = [{ value: null, text: 'Choose a subject' }]
        this.questionSectionIdOptions = [{ value: null, text: 'Choose a section' }]
        for (let i = 0; i < data.question_subjects.length; i++) {
          const questionSubject = data.question_subjects[i]
          this.questionSubjectIdOptions.push({ value: questionSubject.id, text: questionSubject.name })
        }
      }
      console.error(this.questionSubjectIdOptions)
    },
    isDiscussIt (question) {
      try {
        return JSON.parse(question.qti_json).questionType === 'discuss_it'
      } catch (error) {

      }
      return false
    },
    isFlashcard (question) {
      try {
        return JSON.parse(question.qti_json).questionType === 'flashcard'
      } catch (e) {
      }
      return false
    },
    nursingFormattedQuestionTypes () {
      return ['Bow Tie',
        'Multiple Choice',
        'Matrix Multiple Choice',
        'Multiple Response Select N',
        'Multiple Response Select All That Apply',
        'Highlight Table',
        'Highlight Text',
        'Multiple Response Grouping',
        'Drop-Down Table',
        'Drop-Down Dyad',
        'Drop-Down Triad',
        'Drop-Down Cloze',
        'Matrix Multiple Response',
        'Drag and Drop Cloze'
      ]
    },
    initChooseType () {
      if (this.allQuestionsTechnology === 'text') {
        this.$noty.info('Since you have chosen open-ended as your technology, you will only receive open-ended questions.')
        return false
      }
      this.formattedQuestionTypesOptionsByTechnology = this.formattedQuestionTypesOptions
      if (this.allQuestionsTechnology !== 'any') {
        this.formattedQuestionTypesOptionsByTechnology = this.formattedQuestionTypesOptionsByTechnology.filter(item => item.technology === this.allQuestionsTechnology)
        if (['basic', 'nursing', 'sketcher'].includes(this.qtiContentType)) {
          let options
          switch (this.qtiContentType) {
            case ('basic'):
              options = [
                'Multiple Choice', 'True/False', 'Single Numerical', 'Multi Numerical', 'Multiple Answer', 'Fill-in-the-blank', 'Select Choice', 'Matching']
              break
            case ('sketcher'):
              options = ['Submit Molecule', 'Marker']
              break
            case ('nursing'):
              options = this.nursingFormattedQuestionTypes()
              break
          }
          this.formattedQuestionTypesOptionsByTechnology = this.formattedQuestionTypesOptionsByTechnology.filter(item => options.includes(item.formatted_question_type))
        }
      } else {
        const usedFormattedQuestionTypes = []
        const formattedQuestionTypesOptionsByTechnology = []
        for (let i = 0; i < this.formattedQuestionTypesOptionsByTechnology.length; i++) {
          const currentType = this.formattedQuestionTypesOptionsByTechnology[i].formatted_question_type
          if (!usedFormattedQuestionTypes.includes(currentType)) {
            console.log('delete' + currentType)
            formattedQuestionTypesOptionsByTechnology.push(this.formattedQuestionTypesOptionsByTechnology[i])
            usedFormattedQuestionTypes.push(currentType)
          }
        }
        this.formattedQuestionTypesOptionsByTechnology = formattedQuestionTypesOptionsByTechnology
      }

      this.$bvModal.show('modal-formatted-question-types')
    },
    setFormattedQuestionType (formattedQuestionType) {
      this.formattedQuestionType = formattedQuestionType
    },
    async getFormattedQuestionTypes () {
      try {
        const { data } = await axios.get('/api/formatted-question-types')
        this.formattedQuestionTypesOptions = data.formatted_question_types
        const totalOptions = this.formattedQuestionTypesOptions.length
        const columns = totalOptions > 0 ? Math.ceil(totalOptions / 2) : 6
        this.columnSize = 12 / columns
      } catch (error) {
        this.$noty.error(error.message)
      }
    },
    async getShowDescriptions () {
      const { data } = await axios.get('/api/question-bank/show-descriptions')
      this.showDescriptions = +data.show_descriptions
    },
    updateShowDescriptions () {
      this.showDescriptions = !this.showDescriptions
      axios.patch('/api/question-bank/show-descriptions', { show_descriptions: this.showDescriptions })
    },

    clearFilter () {
      this.filter = ''
      this.assignmentQuestions = this.originalAssignmentQuestions
      this.filterMySavedQuestions()
    },
    formatType (type) {
      type = type.replaceAll('_', ' ')
      return _.toLower(type)
    },
    reloadQuestions () {
      if (this.questionSource === 'my_questions') {
        this.getCollection(this.questionSource)
      }
    },
    updateCommonsCourseAssignmentOptions (courseId) {
      console.log(courseId)
      this.commonsCourseAssignmentOptions = this.commonsCourseAssignments[courseId]
      this.commonsCourseAssignment = null
    },
    async getCommonsCoursesAndAssignments () {
      try {
        const { data } = await axios.get('/api/courses/commons-courses-and-assignments')
        if (data.type === 'error') {
          this.$noty.error(data.message)
          return false
        }
        let commonsCoursesAndAssignments = data.commons_courses_and_assignments
        this.commonsCourseOptions = [{ value: null, text: 'Please choose a course' }]
        this.commonsCourseAssignments[null] = [{ value: null, text: 'Please choose an assignment' }]
        for (let i = 0; i < commonsCoursesAndAssignments.length; i++) {
          let item = commonsCoursesAndAssignments[i]
          let courseId = item.course_id
          if (!this.commonsCourseAssignments[courseId]) {
            this.commonsCourseOptions.push({ value: courseId, text: item.course_name })
            this.commonsCourseAssignments[courseId] = [
              {
                value: null,
                text: 'Please choose an assignment'
              }, {
                value: 0,
                text: 'Any assignment'
              }]
          }
          this.commonsCourseAssignments[courseId].push({ value: item.assignment_id, text: item.assignment_name })
        }
        this.commonsCourseAssignmentOptions = this.commonsCourseAssignments[null]
        console.log(this.commonsCourseAssignments)
      } catch (error) {
        this.$noty.error(error.message)
      }
    },
    reloadAllQuestions () {
      this.getCollection('all_questions')
    },
    async filterMySavedQuestions () {
      this.showPagination = false
      await this.getCurrentAssignmentQuestionsBasedOnChosenAssignmentOrSavedQuestionsFolder()
      this.mySavedQuestionsCurrentPage = 1
      this.showPagination = !this.filter
    },
    getBulkActions (questionSource) {
      let bulkActionOptions
      bulkActionOptions = this.bulkActionOptions
      if (!this.withinAssignment) {
        bulkActionOptions = this.bulkActionOptions.filter(option => option.text !== 'Add To Assignment')
      }
      if (questionSource === 'all_questions') {
        bulkActionOptions = bulkActionOptions.filter(option => option.text !== 'Move To Topic')
      } else {
        bulkActionOptions = this.chosenAssignmentId && questionSource === 'my_courses'
          ? bulkActionOptions
          : bulkActionOptions.filter(option => option.text !== 'Move To Topic')
      }
      return bulkActionOptions
    },
    initViewSingleQuestion (assignmentQuestionId) {
      this.selectedQuestionIds = [assignmentQuestionId]
      this.viewSelectedQuestions()
    },
    initRemoveAssignmentQuestion (assignmentQuestion) {
      this.isRemixerTab = true
      this.questionToRemove = assignmentQuestion
      this.openRemoveQuestionModal(assignmentQuestion)
    },
    showAllTopics (boolean) {
      for (let i = 0; i < this.assignments.length; i++) {
        this.assignments[i].topics_shown = boolean
      }
    },
    removeTopic (deletedTopicId, moveToAssignmentId, moveToAssignmentNumQuestions, moveToTopicId, moveToTopicNumQuestions) {
      for (let i = 0; i < this.assignments.length; i++) {
        if (this.assignments[i].id === moveToAssignmentId) {
          this.assignments[i].num_questions = moveToAssignmentNumQuestions
        }
        for (let j = 0; j < this.assignments[i]['topics'].length; j++) {
          console.log(this.assignments[i]['topics'][j])
          if (this.assignments[i]['topics'][j].id === moveToTopicId) {
            this.assignments[i]['topics'][j].num_questions = moveToTopicNumQuestions
          }
          if (this.assignments[i]['topics'][j].id === deletedTopicId) {
            this.assignments[i].topics.splice(j, 1)
          }
        }
      }
    },
    addTopicToList (name, assignmentId, topicId) {
      for (let i = 0; i < this.assignments.length; i++) {
        if (this.assignments[i].id === assignmentId) {
          this.assignments[i].topics.push({ assignment_id: assignmentId, name: name, id: topicId, num_questions: 0 })
          this.assignments[i].topics_shown = true
          this.assignments[i].topics = this.assignments[i].topics.sort(function (a, b) {
            return a.name.toLowerCase() < b.name.toLowerCase() ? -1 : 1
          })
          this.topicsOptions.push({ text: name, value: topicId, assignmentId: assignmentId })
          return
        }
      }
    },
    updateTopicInList (name, topicId) {
      for (let i = 0; i < this.assignments.length; i++) {
        for (let j = 0; j < this.assignments[i]['topics'].length; j++) {
          console.log(this.assignments[i]['topics'][j])
          if (this.assignments[i]['topics'][j].id === topicId) {
            this.assignments[i]['topics'][j].name = name
            return
          }
        }
      }
    },
    async getTopicsByCourse (courseId) {
      const { data } = await axios.get(`/api/assignment-topics/course/${courseId}`)
      if (data.type !== 'success') {
        this.$noty.message(data.message)
      }

      this.topicsOptions = [{ text: 'Choose a topic from within this assignment', value: null }]
      for (let i = 0; i < data.topics.length; i++) {
        let topic = data.topics[i]
        this.topicsOptions.push({ text: topic.name, value: topic.id, assignmentId: topic.assignment_id })
      }
    },
    reloadMyFavoritesOptions () {
      this.saveToMyFavoritesKey++
    },
    filterResults () {
      this.assignmentQuestions = this.originalAssignmentQuestions
      this.assignmentQuestions = this.assignmentQuestions.filter(question =>
        (question.topic && question.topic.toString().includes(this.filter)) ||
        (question.question_id.toString().includes(this.filter)) ||
        (question.text_question && question.text_question.includes(this.filter)) ||
        (question.tags && question.tags.includes(this.filter)) ||
        (question.title && question.title.includes(this.filter))
      )
    },
    getAll () {
      let objectToSum
      let totalQuestions
      totalQuestions = 0
      objectToSum = this.questionChosenFromAssignment() ? this.assignments : this.savedQuestionsFolders
      for (let i = 0; i < objectToSum.length; i++) {
        totalQuestions += objectToSum[i].num_questions
      }
      this.all = {
        name: this.questionChosenFromAssignment()
          ? this.collectionsOptions.find(option => option.value === this.collection).text
          : this.questionSource,
        question_source: this.questionSource,
        num_questions: totalQuestions
      }
    },
    setQuestionId (evt) {
      this.questionIdToMove = evt.item.dataset.questionIdToMove
    },
    async moveToNewTopic (evt) {
      console.log(evt.to)
      if (evt.to.getElementsByClassName('assignment').length || evt.to.getElementsByClassName('all-questions').length) {
        this.$noty.info('You can only move questions from one topic to another topic within the same assignment.')
        await this.getCurrentAssignmentQuestionsBasedOnChosenAssignmentOrSavedQuestionsFolder()
      }
      if (evt.to.getElementsByClassName('topic').length) {
        console.log(evt.to.getElementsByClassName('topic')[0])
        let topicId = evt.to.getElementsByClassName('topic')[0].dataset.topicId
        let assignmentId = evt.to.getElementsByClassName('topic')[0].dataset.assignmentId
        console.log(assignmentId)
        console.log(this.chosenAssignmentId)
        if (parseInt(assignmentId) !== parseInt(this.chosenAssignmentId)) {
          this.$noty.info('You can only move questions to topics within the same assignment.')
          await this.getCurrentAssignmentQuestionsBasedOnChosenAssignmentOrSavedQuestionsFolder()
          return false
        }
        console.log(this.selectedQuestionIds)
        this.selectedQuestionIds = [this.questionIdToMove]
        await this.handleBulkMoveToNewTopic(topicId)
      }
    },
    async handleBulkMoveToNewTopic (topicToMoveQuestionsTo) {
      try {
        const { data } = await axios.patch(`/api/assignment-topics/move/from-assignment/${this.chosenAssignmentId}/to/topic/${topicToMoveQuestionsTo}`,
          { question_ids_to_move: this.selectedQuestionIds })
        this.$noty[data.type](data.message)
        if (data.type !== 'error') {
          await this.getTopicsByAssignment(this.chosenAssignmentId)
          this.$bvModal.hide('modal-bulk-move-to-new-topic')
          this.selectedQuestionIds = []
        }
      } catch (error) {
        this.$noty.error(error.message)
      }
      await this.getCurrentAssignmentQuestionsBasedOnChosenAssignmentOrSavedQuestionsFolder()
    },
    async getTopicsByAssignment (assignmentId) {
      try {
        const { data } = await axios.get(`/api/assignment-topics/assignment/${assignmentId}`)
        if (data.type === 'error') {
          this.$noty.error(data.message)
          return false
        }
        for (let i = 0; i < this.assignments.length; i++) {
          if (this.assignments[i].id === assignmentId) {
            this.assignments[i].topics = data.topics
            this.$forceUpdate()
            return
          }
        }
      } catch (error) {
        this.$noty.error(error.message)
      }
    },
    moveToNewFolder (evt) {
      if (evt.to.getElementsByClassName('saved-questions-folder').length) {
        let toFolderId = evt.to.getElementsByClassName('saved-questions-folder')[0].dataset.folderId
        let fromFolderId = this.chosenAssignmentId
        this.$refs.moveOrRemoveQuestionsMyFavorites.moveQuestionToNewFolder(this.questionIdToMove, fromFolderId, toFolderId)
      } else {
        this.$bvModal.show('modal-cannot-reorder')
      }
    },
    resetBulkActionData () {
      if (!this.questionBankModalShown) {
        this.bulkAction = null
        this.selectedQuestionIds = []
        if (document.getElementsByClassName(`select_all-${this.questionSource}`).length) {
          document.getElementsByClassName(`select_all-${this.questionSource}`)[0].checked = false
        }
      }
    },
    filterByContentType (type) {
      this.assignmentQuestions = this.originalAssignmentQuestions
      switch (type) {
        case ('both'):
          this.assignmentQuestions = this.originalAssignmentQuestions
          break
        case ('auto_graded_only'):
          this.assignmentQuestions = this.originalAssignmentQuestions.filter(question => question.technology !== 'text')
          break
        case ('open_ended_only'):
          this.assignmentQuestions = this.originalAssignmentQuestions.filter(question => question.technology === 'text')
      }
    },
    initRemoveMyFavoritesQuestion (questionToRemoveFromFavoritesFolder) {
      this.questionToRemoveFromFavoritesFolder = questionToRemoveFromFavoritesFolder
      this.$bvModal.show('modal-init-remove-question-from-favorites-folder')
    },
    initSaveToMyFavorites (questionIds) {
      this.savedQuestionsFoldersType = 'my_favorites'
      this.saveToMyFavoritesQuestionIds = questionIds
      console.log(this.saveToMyFavoritesQuestionIds)
      this.$bvModal.show(`modal-save-to-my-favorites-${this.questionSource}`)
    },
    actOnBulkAction (action) {
      if (action === null) return
      this.savedQuestionsFoldersType = 'my_favorites'
      switch (action) {
        case ('view'):
          this.viewSelectedQuestions()
          break
        case ('copy_adapt_ids'):
          this.doCopy('adapt-ids')
          this.bulkAction = null
          break
        case ('add_to_assignment'):
          this.convertQuestionIdsToAddToQuestionsToAdd(this.selectedQuestionIds)
          break
        case ('bulk_move_to_new_topic'):
          this.filteredTopicsOptions = this.topicsOptions.filter(topic => topic.assignmentId === this.chosenAssignmentId)
          this.filteredTopicsOptions = this.filteredTopicsOptions.filter(topic => topic.value !== this.chosenTopicId)
          let defaultOption = {
            value: null,
            text: 'Please choose another topic within this assignment'
          }
          if (this.filteredTopicsOptions.length) {
            this.filteredTopicsOptions.unshift(defaultOption)
          } else {
            this.filteredTopicsOptions = [defaultOption]
          }
          this.topicToMoveQuestionsTo = null
          this.$bvModal.show('modal-bulk-move-to-new-topic')
          break
        default:
          alert(`${action} is not a valid action`)
      }
    },
    getQuestionSourceText () {
      if (this.questionSource) {
        return _.startCase(this.questionSource.replace('_', ' '))
      }
    },
    resetFolderAction () {
      this.folderAction = null
    },
    async initFolderAction (action, questionsFolder = {}) {
      switch (action) {
        case ('new'):
          this.$refs.moveOrRemoveQuestionsMyFavorites.initCreateSavedQuestionsFolder(this.isTopic, this.chosenAssignment.id)
          break
        case ('edit'):
          let chosenTopicAssignmentId = this.isTopic
            ? this.chosenTopicAssignmentId
            : null
          this.$refs.moveOrRemoveQuestionsMyFavorites.initUpdateSavedQuestionsFolder(this.isTopic, chosenTopicAssignmentId, questionsFolder)
          break
        case ('delete'):
          // only allow them to be moved within the current assignment
          let topicsOptions = this.topicsOptions.filter(topic => topic.assignmentId === this.chosenTopicAssignmentId)
          this.$refs.moveOrRemoveQuestionsMyFavorites.initDeleteSavedQuestionsFolder(this.isTopic, topicsOptions, questionsFolder, this.questionSource)
          break
      }
    },
    initMoveOrRemoveSavedQuestion (question) {
      this.$refs.moveOrRemoveQuestionsMyFavorites.initMoveOrRemoveSavedQuestion(question)
    },
    setSavedQuestionsFolder (savedQuestionsFolder) {
      this.savedQuestionsFolder = savedQuestionsFolder
    },
    fixQuestionBankScrollHeight () {
      this.questionBankScrollHeight = (window.screen.height - 200) + 'px'
    },
    updateModalTitle () {
      if (document.getElementById(`question-bank-view-questions-${this.questionSource}___BV_modal_title`)) {
        document.getElementById(`question-bank-view-questions-${this.questionSource}___BV_modal_title`).innerHTML = this.questionToView.title
      }
    },
    setQuestionToView (questionToView) {
      this.questionToView = questionToView
      let assignmentQuestion = this.assignmentQuestions.find(question => question.question_id === this.questionToView.question_id)
      this.questionToView.in_current_assignment = assignmentQuestion.in_current_assignment
      this.questionToView.solution_type = questionToView.solution_html ? 'html' : null
      this.questionToView.my_favorites_folder_id = assignmentQuestion.my_favorites_folder_id
      this.questionToView.my_favorites_folder_name = assignmentQuestion.my_favorites_folder_name
      this.questionToView.saved_question_folder = assignmentQuestion.saved_question_folder
      this.questionToView.library_page_id = assignmentQuestion.library_page_id
      this.questionToView.technology_id = assignmentQuestion.technology_id
      this.$forceUpdate()
      setTimeout(this.updateModalTitle, 100)
      setTimeout(this.updateModalTitle, 500)
      setTimeout(this.updateModalTitle, 1000)
    },
    async removeMyFavoritesQuestion (folderId, questionId) {
      try {
        const { data } = await axios.delete(`/api/my-favorites/folder/${folderId}/question/${questionId}`)
        if (data.type === 'error') {
          this.$noty.error(data.message)
          return false
        }
        this.$root.$emit('bv::hide::tooltip')
        this.$bvModal.hide('modal-move-or-remove-question')
        this.$bvModal.hide('modal-init-remove-question-from-favorites-folder')
        this.questionSource === 'all_questions'
          ? await this.getCollection('all_questions')
          : await this.getCurrentAssignmentQuestionsBasedOnChosenAssignmentOrSavedQuestionsFolder(false)
        if (this.questionSource === 'my_favorites') {
          await this.getCollection('my_favorites', folderId)
        }
        if (this.questionBankModalShown) {
          this.setQuestionToView(this.questionToView)
        }
      } catch (error) {
        this.$noty.error(error.message)
      }
    },
    async saveMyFavoritesQuestions () {
      console.log(this.saveToMyFavoritesQuestionIds)
      if (this.savedQuestionsFolder === null) {
        this.$noty.info('Please first choose a Favorites folder.')
        return false
      }
      try {
        let chosenAssignmentIds
        if (this.$route.name === 'question.editor') {
          chosenAssignmentIds = [0]
        } else {
          if (['my_questions', 'all_questions'].includes(this.questionSource)) {
            chosenAssignmentIds = [0]
          }
          if (this.chosenAssignmentId) {
            chosenAssignmentIds = [this.chosenAssignmentId]
          }
        }
        if (this.chosenCourseId) {
          chosenAssignmentIds = []
          for (let i = 0; i < this.saveToMyFavoritesQuestionIds.length; i++) {
            let questionId = this.saveToMyFavoritesQuestionIds[i]
            chosenAssignmentIds.push(this.assignmentQuestions.find(question => question.question_id === questionId).assignment_id)
          }
        }
        const { data } = await axios.post('/api/my-favorites',
          {
            question_ids: this.saveToMyFavoritesQuestionIds,
            folder_id: this.savedQuestionsFolder,
            chosen_assignment_ids: chosenAssignmentIds
          })

        if (data.type === 'error') {
          this.$noty.error(data.message)
          return false
        }
        this.questionSource === 'all_questions'
          ? await this.getCollection('all_questions')
          : await this.getCurrentAssignmentQuestionsBasedOnChosenAssignmentOrSavedQuestionsFolder()
        if (this.questionBankModalShown) {
          this.setQuestionToView(this.questionToView)
        }
      } catch (error) {
        this.$noty.error(error.message)
      }
      this.$bvModal.hide(`modal-save-to-my-favorites-${this.questionSource}`)
    },
    async removeQuestionFromRemixedAssignment (questionId) {
      this.$bvModal.hide('modal-remove-question')
      this.$bvModal.hide(`modal-view-question-${this.typeOfRemixer}`)

      try {
        const { data } = await axios.delete(`/api/assignments/${this.assignmentId}/questions/${questionId}`)
        this.$noty[data.type](data.message)
        if (data.type !== 'error') {
          if (this.typeOfRemixer === 'saved-questions') {
            this.publicCourseAssignmentQuestions = this.originalChosenPublicCourseAssignmentQuestions
          } else {
            if (this.questionSource === 'my_courses' &&
              this.chosenAssignmentId &&
              this.assignmentId &&
              parseInt(this.chosenAssignmentId) === parseInt(this.assignmentId)) {
              await this.getTopicsByAssignment(this.chosenAssignmentId)
            }
            this.questionSource === 'all_questions'
              ? await this.getCollection('all_questions')
              : await this.getCurrentAssignmentQuestionsBasedOnChosenAssignmentOrSavedQuestionsFolder(false)
            if (this.questionBankModalShown) {
              this.questionToView.in_assignment = this.assignmentQuestions.find(question => question.question_id === this.questionToView.question_id).in_assignment === true
              this.$forceUpdate()
            }
          }
          await this.getQuestionWarningInfo()
        }
      } catch (error) {
        this.$noty.error(error.message)
      }
    },
    initGetQuestionSource (questionSource) {
      this.savedQuestionsFolders = this.assignments = this.assignmentQuestions = []
      this.collection = null
      if (!['commons', 'my_courses', 'all_public_courses', 'all_questions', 'my_questions', 'my_favorites'].includes(questionSource)) {
        if (questionSource) {
          alert(`${questionSource} is not a valid question source`)
        }
        return false
      }

      this.questionChosenFromAssignment()
        ? this.getCollections(questionSource)
        : this.getCollection(questionSource)
    },
    async getCollections (questionSource) {
      this.resetBulkActionData()
      this.publicCourse = null
      try {
        let url
        let collectionName
        let defaultText
        switch (questionSource) {
          case ('commons'):
            url = '/api/courses/commons'
            collectionName = 'commons_courses'
            defaultText = 'collection'
            break
          case ('my_courses'):
            collectionName = 'courses'
            defaultText = 'course'
            url = '/api/courses'
            break
          case ('all_public_courses'):
            collectionName = 'public_courses'
            defaultText = 'course'
            url = '/api/courses/public'
            break
        }
        this.collectionsOptions = [{ value: null, text: `Please choose a ${defaultText}` }]
        const { data } = await axios.get(url)
        console.log(data)
        if (data.type !== 'success') {
          this.$noty.error(data.message)
          return false
        }
        if (data[collectionName]) {
          for (let i = 0; i < data[collectionName].length; i++) {
            let course = data[collectionName][i]
            let text = course.name
            if (questionSource === 'all_public_courses' && course.instructor === 'Commons Instructor') {
              continue
            }
            if (this.questionSource === 'my_courses' && course.term !== 'N/A' && course.term) {
              text += ` (${course.term})`
            }
            if (this.questionSource === 'all_public_courses') {
              text += ` --- ${course.instructor}`
              if (course.school !== 'Not Specified') {
                text += `/${course.school}`
              }
            }
            let publicCourse = { value: course.id, text: text }
            this.collectionsOptions.push(publicCourse)
          }
        }
      } catch (error) {
        this.$noty.error(error.message)
      }
    },
    convertQuestionIdsToAddToQuestionsToAdd (questionIdsToAdd) {
      let questionsToAdd = []
      for (let i = 0; i < this.assignmentQuestions.length; i++) {
        let question = this.assignmentQuestions[i]
        if (questionIdsToAdd.includes(question.question_id)) {
          questionsToAdd.push(question)
        }
      }
      this.addQuestions(questionsToAdd)
    },
    async checkForDiscussItQuestions (questionsToAdd) {
      try {
        const { data } = await axios.post('/api/assignment-sync-question/check-for-discuss-it-questions-over-multiple-assignment-questions',
          questionsToAdd)
        if (data.type === 'success') {
          this.checkedForDiscussItQuestions = true
          if (data.discuss_it_question_exists) {
            this.questionsToAdd = questionsToAdd
            this.$bvModal.show('modal-discussion-questions-exist')
          } else {
            await this.addQuestions(questionsToAdd)
          }
        } else {
          this.$noty.error(data.message)
        }
      } catch (error) {
        this.$noty.error(error.message)
      }
    },
    async addQuestions (questionsToAdd) {
      this.$root.$emit('bv::hide::tooltip')
      if (['commons', 'my_courses', 'all_public_courses'].includes(this.questionSource)) {
        for (let i = 0; i < questionsToAdd.length; i++) {
          questionsToAdd[i].assignment_id = this.chosenAssignmentId
            ? this.chosenAssignmentId
            : this.assignmentQuestions.find(question => question.question_id === questionsToAdd[i].question_id).assignment_id
        }
        if (!this.checkedForDiscussItQuestions) {
          this.resetDiscussItSettingsToDefault = '1'
          await this.checkForDiscussItQuestions(questionsToAdd)
          return
        }
        this.$bvModal.hide('modal-discussion-questions-exist')
      }
      try {
        const { data } = await axios.patch(`/api/assignments/${this.assignmentId}/remix-assignment-with-chosen-questions`,
          {
            'chosen_questions': questionsToAdd,
            'question_source': this.questionSource,
            'reset_discuss_it_settings_to_default': this.resetDiscussItSettingsToDefault
          })
        if (data.type === 'error') {
          this.$noty.error(data.message, {
            timeout: 8000
          })
        }
        if (data.type === 'success') {
          for (let i = 0; i < questionsToAdd.length; i++) {
            this.assignmentQuestions.find(question => question.question_id === questionsToAdd[i].question_id).in_current_assignment = true
            this.$forceUpdate()
          }
          if (this.questionBankModalShown) {
            this.setQuestionToView(this.questionToView)
          } else {
            this.selectedQuestionIds = []
            if (document.getElementsByClassName(`select_all-${this.questionSource}`)) {
              document.getElementsByClassName(`select_all-${this.questionSource}`)[0].checked = false
            }
          }
        }
      } catch (error) {
        this.$noty.error(error.message)
      }
      this.checkedForDiscussItQuestions = false
      await this.getQuestionWarningInfo()

      if (this.typeOfRemixer === 'saved-questions') {
        this.publicCourseAssignmentQuestions = this.originalChosenPublicCourseAssignmentQuestions
      }
    },
    viewSelectedQuestions () {
      this.numViewSelectedQuestionsClicked++
      this.$bvModal.show(`question-bank-view-questions-${this.questionSource}`)
    },

    selectAll () {
      this.selectedQuestionIds = []
      console.log(`selected-question-id-${this.questionSource}`)
      let checkboxes = document.getElementsByClassName(`selected-question-id-${this.questionSource}`)
      console.log(checkboxes)
      console.log(document.getElementsByClassName(`select_all-${this.questionSource}`)[0].checked)
      if (document.getElementsByClassName(`select_all-${this.questionSource}`)[0].checked) {
        for (let checkbox of checkboxes) {
          // was not updating the visible dom so I did both an add to the array and dom manipulation
          this.selectedQuestionIds.push(parseInt(checkbox.value))
          checkbox.checked = true
        }
      }
    },
    questionChosenFromAssignment () {
      return !['my_favorites', 'my_questions', 'all_questions'].includes(this.questionSource)
    },
    async reloadQuestionsAfterEdit () {
      this.questionSourceKey++
      this.questionSource === 'all_questions'
        ? await this.getCollection('all_questions')
        : await this.getCurrentAssignmentQuestionsBasedOnChosenAssignmentOrSavedQuestionsFolder()
    },
    async getCurrentAssignmentQuestionsBasedOnChosenAssignmentOrSavedQuestionsFolder (clearAll = true) {
      if (!this.questionBankModalShown && clearAll) {
        this.processingGetCollection = true
        this.assignmentQuestions = []
      }
      try {
        let folderInformation
        folderInformation = {
          user_assignment_id: this.$route.params.assignmentId,
          collection_type: this.questionChosenFromAssignment() ? 'assignment' : this.questionSource
        }
        if (this.questionChosenFromAssignment()) {
          this.chosenCourseId
            ? folderInformation.course_id = this.chosenCourseId
            : folderInformation.assignment_id = this.chosenAssignmentId
          if (this.chosenTopicId) {
            folderInformation.topic_id = this.chosenTopicId
          }
        } else {
          this.allSavedQuestionFoldersByQuestionSource
            ? folderInformation.folder_id = 'all_folders'
            : folderInformation.folder_id = this.chosenAssignmentId
        }
        // for my questions which could get large
        folderInformation.current_page = this.mySavedQuestionsCurrentPage
        folderInformation.per_page = this.perPageMinMySavedQuestions
        folderInformation.filter = this.filter

        //
        const { data } = await axios.post('/api/question-bank/potential-questions-with-course-level-usage-info', folderInformation)
        if (data.type === 'error') {
          this.$noty.error(data.message)
          this.chosenCourseId = null
          return false
        }
        this.originalAssignmentQuestions = data.assignment_questions
        for (let i = 0; i < this.originalAssignmentQuestions.length; i++) {
          if (this.originalAssignmentQuestions[i].topic === null) {
            this.originalAssignmentQuestions[i].topic = 'None'
          }
        }
        this.resetBulkActionData()
        this.filterByContentType(this.content)
      } catch (error) {
        this.$noty.error(error.message)
      }
      this.processingGetCollection = false
    },
    async getCollection (collection, toFolderId = null) {
      this.showPagination = true
      if (this.allQuestionsAdaptId) {
        this.showPagination = false
        this.allQuestionsCurrentPage = 1
        this.allQuestionsContent = 'both'
        this.allQuestionsTechnology = 'any'
        this.allQuestionsTechnologyId = ''
        this.allQuestionsTitle = ''
        this.allQuestionsAuthor = ''
        this.allQuestionsTags = ''
      }
      this.processingGetCollection = true
      this.chosenCourseId = null
      this.resetBulkActionData()
      let url
      let allQuestionsData
      switch (this.questionSource) {
        case ('my_courses'):
          url = `/api/assignments/courses/${collection}`
          break
        case ('all_public_courses'):
          url = `/api/assignments/courses/public/${collection}/names`
          break
        case ('all_questions'):
          let questionType
          questionType = this.formattedQuestionType
          if (this.allQuestionsTechnology === 'qti') {
            if (this.qtiContentType === 'discuss_it') {
              questionType = this.qtiContentType
            }
          }
          allQuestionsData = {
            question_subject_id: this.questionSubjectId,
            question_chapter_id: this.questionChapterId,
            question_section_id: this.questionSectionId,
            current_page: this.allQuestionsCurrentPage,
            question_id: this.allQuestionsAdaptId,
            per_page: this.allQuestionsPerPage,
            question_class: this.allQuestionsClass,
            question_type: questionType,
            question_content: this.allQuestionsContent,
            technology: this.allQuestionsTechnology,
            webwork_content_type: this.webworkContentType,
            webwork_algorithmic: this.webworkAlgorithmic,
            qti_question_type: this.allQuestionsTechnology === 'qti' ? this.qtiQuestionType : '',
            qti_content_type: this.allQuestionsTechnology === 'qti' ? this.qtiContentType : '',
            technology_id: this.allQuestionsTechnologyId,
            course_id: this.commonsCourse,
            assignment_id: this.commonsCourseAssignment,
            title: this.allQuestionsTitle,
            author: this.allQuestionsAuthor,
            tags: this.allQuestionsTags,
            user_assignment_id: this.$route.params.assignmentId
          }
          url = `/api/question-bank/all`
          break
        case ('commons'):
          url = `/api/assignments/open/commons/${collection}`
          break
        case ('my_favorites'):
        case ('my_questions'):
          if (this.questionSource === 'my_questions') {
            this.centrifuge = await initCentrifuge()
            const sub = this.centrifuge.newSubscription(`saved-questions-folders-my_questions-${this.user.id}`)
            const getMyQuestions = this.getMyQuestions
            sub.on('publication', async function (ctx) {
              await getMyQuestions(ctx)
            }).subscribe()
          }
          this.moveOrRemoveQuestionsMyFavoritesKey++
          url = `/api/saved-questions-folders/${this.questionSource}/${this.withH5p}`
          break
        default:
          alert(`${collection} does not exist.  Please contact us.`)
          return false
      }
      try {
        const { data } = this.questionSource === 'all_questions'
          ? await axios.post(url, allQuestionsData)
          : await axios.get(url)
        if (data.type === 'error') {
          this.$noty.error(data.message)
          return false
        }

        if (this.questionSource === 'all_questions') {
          this.assignmentQuestions = data.all_questions
          this.allQuestionsTotalRows = data.total_rows
          this.isLoading = false
        } else if (this.questionSource !== 'my_questions') {
          if (this.questionChosenFromAssignment()) {
            data.assignments.forEach(function (assignment) {
              assignment.topics_shown = false
            })
            this.assignments = data.assignments
          } else {
            this.savedQuestionsFolders = data.saved_questions_folders
            if (this.savedQuestionsFolders[0]) {
              this.mySavedQuestionsTotalRows = this.savedQuestionsFolders[0].num_questions
            }
          }
          this.chosenAssignmentId = this.questionChosenFromAssignment()
            ? this.assignments[0].id
            : toFolderId || this.savedQuestionsFolders[0].id

          if (this.questionSource === 'my_courses') {
            await this.getTopicsByCourse(this.collection)
          }
          await this.getCurrentAssignmentQuestionsBasedOnChosenAssignmentOrSavedQuestionsFolder(this.chosenAssignmentId)
          this.updatedDraggable++
          this.assignmentQuestionsKey++
          this.getAll()
          this.isLoading = false
        }
      } catch (error) {
        this.$noty.error(error.message)
      }
      this.processingGetCollection = false
    },
    async getMyQuestions (ctx) {
      const data = ctx.data
      if (data.error_message) {
        this.$noty.error(data.error_message)
        this.isLoading = false
        return false
      }

      this.savedQuestionsFolders = JSON.parse(data.saved_questions_folders)
      console.log(this.savedQuestionsFolders)
      if (this.savedQuestionsFolders[0]) {
        this.mySavedQuestionsTotalRows = this.savedQuestionsFolders[0].num_questions
      }

      this.chosenAssignmentId = this.savedQuestionsFolders[0].id
      await this.getCurrentAssignmentQuestionsBasedOnChosenAssignmentOrSavedQuestionsFolder(this.chosenAssignmentId)
      this.updatedDraggable++
      this.assignmentQuestionsKey++
      this.getAll()
      this.isLoading = false
    },
    resetDirectImportMessages () {
      this.directImportIdsAddedToAssignmentMessage = ''
      this.errorDirectImportIdsMessage = ''
      this.directImportIdsNotAddedToAssignmentMessage = ''
    },
    setQuestionToRemove (questionToRemove, chosenAssignmentId) {
      this.questionToRemove = questionToRemove
      this.chosenAssignmentId = chosenAssignmentId
      this.$bvModal.show('modal-remove-question')
    },
    submitRemoveQuestion () {
      this.isRemixerTab ? this.removeQuestionFromRemixedAssignment(this.questionToRemove.question_id) : this.removeQuestionFromSearchResult(this.questionToRemove)
    },
    openRemoveQuestionModal () {
      this.$bvModal.show('modal-remove-question')
    },
    async getQuestionWarningInfo () {
      try {
        const { data } = await axios.get(`/api/assignments/${this.assignmentId}/questions/summary`)
        if (data.type === 'error') {
          this.$noty.error(data.message)
          return false
        }
        this.betaAssignmentsExist = data.beta_assignments_exist
        this.h5pQuestionsWithAnonymousUsers = data.h5p_questions_exist && data.course_has_anonymous_users
        this.isPointsByQuestionWeightAndSubmissionsExist = data.is_question_weight && data.submissions_exist
        this.assessmentTypeWarningsKey = 1
        this.items = data.rows
        let hasNonH5P
        for (let i = 0; i < this.items.length; i++) {
          if (this.items[i].submission !== 'h5p') {
            hasNonH5P = true
          }
          if (this.assessmentType !== 'delayed' && !this.items[i].auto_graded_only) {
            this.openEndedQuestionsInRealTime += this.items[i].order + ', '
          }
        }
        this.updateLearningTreeInNonLearningTreeMessage()
        this.updateNonLearningTreeInLearningTreeMessage()

        if (this.assessment_type === 'clicker' && hasNonH5P) {
          this.$bvModal.show('modal-non-h5p')
        }
      } catch (error) {
        this.$noty.error(error.message)
      }
    },
    async getDefaultImportLibrary () {
      try {
        const { data } = await axios.get('/api/questions/default-import-library')
        this.defaultImportLibrary = data.default_import_library
      } catch (error) {
        this.$noty.error(error.message)
      }
    },
    async setDefaultImportLibrary () {
      try {
        const { data } = await axios.post('/api/questions/default-import-library', { 'default_import_library': this.defaultImportLibrary })
        this.$noty[data.type](data.message)
      } catch (error) {
        this.$noty.error(error.message)
      }
    },
    resetDirectImport () {
      this.questions = []
      this.directImportIdsAddedToAssignmentMessage = ''
      this.directImportIdsNotAddedToAssignmentMessage = ''
      this.directImport = ''
    },
    resetSearchByTag () {
      this.showQuestions = false
      this.chosenTags = []
    },
    async directImportQuestions (type) {
      this.$bvModal.hide('modal-discussion-questions-exist')
      if (this.directImportingQuestions) {
        let timeToProcess = Math.ceil(((this.directImport.match(/,/g) || []).length) / 3)
        let message = `Please be patient.  Validating all of your Libretexts Ids  will take about ${timeToProcess} seconds.`
        this.$noty.info(message)
        return false
      }

      this.pageIdsAddedToAssignmentMessage = ''
      this.pageIdsNotAddedToAssignmentMessage = ''
      this.errorDirectImportIdsMessage = ''
      this.directImportIdsAddedToAssignmentMessage = ''
      this.directImportIdsNotAddedToAssignmentMessage = ''
      this.directImportingQuestions = true
      let directImport = this.directImport.split(',')
      this.directImportCount = directImport.length
      let directImportIdsAddedToAssignment = []
      let directImportIdsNotAddedToAssignment = []
      let errorDirectImportIds = []
      for (this.directImportIndex = 0; this.directImportIndex < directImport.length; this.directImportIndex++) {
        try {
          const { data } = await axios.post(`/api/questions/${this.assignmentId}/direct-import-question`,
            {
              'direct_import': directImport[this.directImportIndex],
              'type': type
            }
          )
          if (data.type === 'error') {
            errorDirectImportIds.push(directImport[this.directImportIndex])
            this.$noty.error(data.message)
          }
          if (data.direct_import_id_added_to_assignment) {
            directImportIdsAddedToAssignment.push(data.direct_import_id_added_to_assignment)
          }
          if (data.direct_import_id_not_added_to_assignment) {
            directImportIdsNotAddedToAssignment.push(data.direct_import_id_not_added_to_assignment)
          }
        } catch (error) {
          this.$noty.error(error.message)
        }
      }
      this.directImportingQuestions = false
      directImportIdsAddedToAssignment = directImportIdsAddedToAssignment.join(', ')
      directImportIdsNotAddedToAssignment = directImportIdsNotAddedToAssignment.join(', ')
      let verb
      verb = directImportIdsAddedToAssignment.includes(',') ? 'were' : 'was'
      if (directImportIdsAddedToAssignment !== '') {
        this.directImportIdsAddedToAssignmentMessage = `${directImportIdsAddedToAssignment} ${verb} added to this assignment.`
      }
      if (errorDirectImportIds.length) {
        this.errorDirectImportIdsMessage = `Errors found with: ${errorDirectImportIds}`
      }
      verb = directImportIdsNotAddedToAssignment.includes(',') ? 'were' : 'was'
      let pronoun = directImportIdsNotAddedToAssignment.includes(',') ? 'they' : 'it'
      if (directImportIdsNotAddedToAssignment !== '') {
        this.directImportIdsNotAddedToAssignmentMessage = `${directImportIdsNotAddedToAssignment} ${verb} not added to this assignment since ${pronoun} ${verb} already a part of the assignment.`
      }
      this.directImport = ''
    },
    openUploadFileModal (questionId) {
      this.uploadFileForm.errors.clear(this.uploadFileType)
      this.uploadFileForm.questionId = questionId
      this.uploadFileForm.assignmentId = this.assignmentId
    },
    async handleOk (bvModalEvt) {
      // Prevent modal from closing
      bvModalEvt.preventDefault()
      // Trigger submit handler
      if (this.uploading) {
        this.$noty.info('Please be patient while the file is uploading.')
        return false
      }
      this.uploading = true
      try {
        await this.submitUploadFile('solution', this.uploadFileForm, this.$noty, this.$nextTick, this.$bvModal, this.questions[this.currentPage - 1], '/api/solution-files')
      } catch (error) {
        this.$noty.error(error.message)
      }
      this.uploading = false
    },
    async getAssignmentInfo () {
      try {
        const { data } = await axios.get(`/api/assignments/${this.assignmentId}/get-questions-info`)
        if (data.type === 'error') {
          this.$noty.error(data.message)
          return false
        }

        let assignment = data.assignment
        this.isFormative = assignment.formative
        this.assignmentName = assignment.name
        this.title = `Add Questions to "${this.assignmentName}"`
        this.assessmentType = assignment.assessment_type
        this.questionFilesAllowed = (assignment.submission_files === 'q')// can upload at the question level
      } catch (error) {
        this.title = 'Add Questions'
      }
      if (this.continueLoading) { // OK to load the rest of the page
        h5pResizer()
      }
    },
    changePage (currentPage) {
      this.$nextTick(() => {
        let iframeId = this.questions[currentPage - 1].iframe_id
        iFrameResize({ log: false }, `#${iframeId}`)
        iFrameResize({ log: false }, '#non-technology-iframe')
      })
    },
    removeTag (chosenTag) {
      this.chosenTags = _.without(this.chosenTags, chosenTag)
      this.questions = []
    },
    addTag () {
      if (this.chosenTags.length === 0 && this.query === '') {
        this.$noty.error('You did not include a tag.')
        return false
      }

      if (!this.tags.includes(this.query)) {
        this.$noty.error(`The tag <strong>${this.query}</strong> does not exist in our database.`)
        return false
      }

      if (!this.chosenTags.includes(this.query)) {
        this.chosenTags.push(this.query)
      }
      return true
    },
    async addQuestion (question) {
      try {
        this.questions[this.currentPage - 1].questionFiles = false
        const { data } = await axios.post(`/api/assignments/${this.assignmentId}/questions/${question.id}`)
        this.$noty[data.type](data.message)
        if (data.type === 'success') {
          this.questions[this.currentPage - 1].inAssignment = true
        }
      } catch (error) {
        this.$noty.error('We could not add the question to the assignment.  Please try again or contact us for assistance.')
      }
    },
    async removeQuestionFromSearchResult (question) {
      this.$bvModal.hide('modal-remove-question')
      try {
        const { data } = await axios.delete(`/api/assignments/${this.assignmentId}/questions/${question.id}`)
        if (data.type === 'info') {
          this.$noty.info(data.message)
          this.questions[this.currentPage - 1].inAssignment = false
        } else {
          this.$noty.error(data.message)
        }
      } catch (error) {
        this.$noty.error('We could not remove the question from the assignment.  Please try again or contact us for assistance.')
      }
    },
    async getStudentView (assignmentId) {
      await this.$router.push(`/assignments/${assignmentId}/questions/view`)
    }
  },
  metaInfo () {
    return { title: 'Get Questions' }
  }
}
</script>
<style scoped>
.question-bank-scroll {
  overflow-y: auto;
}

.wrapWord {
  word-wrap: break-word;
  max-width: 150px;
}

.header {
  position: sticky;
  top: 0;
}

.saved-question-folder-list {
  border-bottom: 0
}

thead th {
  background-color: white;
  color: black;
}
</style>
<style>
body, html {
  overflow: visible;

}

input[type=checkbox] {
  transform: scale(1.25);
}

</style>
