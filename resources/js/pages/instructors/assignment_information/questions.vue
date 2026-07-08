<template>
  <div>
    <AllFormErrors :all-form-errors="allFormErrors" :modal-id="'modal-form-errors-learning-tree-questions'"/>
    <LearningTreeProperties v-if="assessmentType === 'learning tree'"
                            :learning-tree-form="learningTreeForm"
                            :learning-tree-id="learningTreeId"
                            :can-edit-learning-tree="canEditLearningTree"
                            @saveLearningTreeProperties="saveLearningTreeProperties"
    />
    <CannotDeleteAssessmentFromBetaAssignmentModal/>
    <b-modal
      id="modal-confirm-remove-open-ended-component"
      no-close-on-backdrop
      no-close-on-esc
      size="lg"
      title="Confirm Remove Open-ended Components"
    >
      <p>The following questions have open-ended components that may be removed to make the questions fully
        auto-graded. All open-ended submissions will be removed.</p>
      <b-table aria-label="Open-ended components to remove"
               striped
               hover
               title="Questions with open-ended components"
               :fields="openEndedComponentsToRemoveFields"
               :items="openEndedComponentsToRemove"
      />
      <template #modal-footer="{ cancel, ok }">
        <b-button size="sm"
                  @click="$bvModal.hide('modal-confirm-remove-open-ended-component')"
        >
          Close
        </b-button>
        <b-button size="sm"
                  variant="primary"
                  @click="removeOpenEndedComponents()"
        >
          Remove Open-ended Components
        </b-button>
      </template>
    </b-modal>
    <b-modal
      v-if="questionToEdit"
      :id="`modal-edit-question-${questionToEdit.id}`"
      :key="`modal-edit-question-${questionToEdit.id}`"
      size="xl"
      no-close-on-backdrop
      hide-footer
      @hidden="$emit('reloadCurrentAssignmentQuestions')"
    >
      <template #modal-header>
        <div>
          <h2 class="h5 modal-title">
            Edit Question "{{ questionToEdit.title }}"
          </h2>
          <div>
            <small>ADAPT ID: <span :id="`adapt-id-${questionToEdit.id}`">{{ questionToEdit.id }}</span></small>
            <span class="text-info">
              <a href=""
                 aria-label="Copy ADAPT ID"
                 @click.prevent="doCopy(`adapt-id-${questionToEdit.id}`)"
              >
                <font-awesome-icon :icon="copyIcon"/>
              </a>
            </span>
          </div>
        </div>
        <button type="button" aria-label="Close"
                class="close"
                @click="$bvModal.hide(`modal-edit-question-${questionToEdit.id}`);$emit('reloadCurrentAssignmentQuestions')"
        >
          ×
        </button>
      </template>
      <CreateQuestion :key="`question-to-edit-${questionToEdit.id}-${questionToEdit.question_revision_id}`"
                      :question-to-edit="questionToEdit"
                      :parent-get-my-questions="getAssignmentInfo"
                      :modal-id="'my-questions-question-to-view-questions-editor'"
                      :question-exists-in-own-assignment="questionToEdit.question_exists_in_own_assignment"
                      :question-exists-in-another-instructors-assignment="questionToEdit.question_exists_in_another_instructors_assignment"
                      @setQuestionRevision="setQuestionRevision"
      />
    </b-modal>
    <b-modal
      id="modal-confirm-refresh-questions-and-properties"
      title="Confirm refresh questions and properties"
    >
      <b-form-checkbox
        id="checkbox-1"
        v-model="confirmedRefreshQuestionsAndProperties"
        name="confirm-"
        :value="true"
        :unchecked-value="false"
      >
        I understand that the question will be refreshed in all ADAPT assignments where this question exists. Student
        submissions will not be removed.
      </b-form-checkbox>
      <template #modal-footer="{ cancel, ok }">
        <b-button size="sm" @click="$bvModal.hide('modal-confirm-refresh-questions-and-properties')">
          Cancel
        </b-button>
        <b-button size="sm" variant="primary"
                  @click="refreshQuestions('questions_and_properties')"
        >
          Submit
        </b-button>
      </template>
    </b-modal>
    <b-modal
      v-if="alphaAssignmentQuestion"
      id="modal-view-question"
      ref="modalViewQuestion"
      title="View Question"
      size="lg"
    >
      <div>
        <iframe v-show="alphaAssignmentQuestion.non_technology"
                :key="`non-technology-iframe-${alphaAssignmentQuestion.id}`"
                v-resize="{checkOrigin: false }"
                width="100%"
                :src="alphaAssignmentQuestion.non_technology_iframe_src"
                frameborder="0"
        />
      </div>

      <div v-if="alphaAssignmentQuestion.technology_iframe">
        <iframe
          :key="`technology-iframe-${alphaAssignmentQuestion.id}`"
          v-resize="{ checkOrigin: false }"
          width="100%"
          :src="alphaAssignmentQuestion.technology_iframe"
          frameborder="0"
        />
      </div>
      <template #modal-footer>
        <b-button
          v-show="viewQuestionAction==='add'"
          size="sm"
          class="float-right"
          variant="primary"
          @click="addQuestionFromAlphaAssignment()"
        >
          Add Question
        </b-button>
        <b-button
          v-show="viewQuestionAction==='remove'"
          size="sm"
          class="float-right"
          variant="danger"
          @click="removeQuestionFromBetaAssignment()"
        >
          Remove Question
        </b-button>
      </template>
    </b-modal>
    <b-modal
      id="modal-remove-question"
      ref="modal"
      title="Confirm Remove Question"
    >
      <RemoveQuestion :beta-assignments-exist="betaAssignmentsExist"/>
      <template #modal-footer>
        <b-button
          size="sm"
          class="float-right"
          @click="$bvModal.hide('modal-remove-question')"
        >
          Cancel
        </b-button>
        <b-button
          variant="primary"
          size="sm"
          class="float-right"
          @click="submitRemoveQuestion()"
        >
          Yes, remove question!
        </b-button>
      </template>
    </b-modal>
    <b-modal
      id="modal-non-h5p"
      ref="h5pModal"
      title="Non-H5P assessments in clicker assignment"
    >
      <b-alert :show="true" variant="danger">
        <span class="font-weight-bold">
          {{ h5pText }}
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
      <div v-if="!isLoading">
        <PageTitle :title="assessmentType === 'learning tree' ? 'Trees' : 'Questions'"
                   :assessment-type="assessmentType"
        />
        <AssessmentTypeWarnings :assessment-type="assessmentType"
                                :open-ended-questions-in-real-time="openEndedQuestionsInRealTime"
                                :learning-tree-questions-in-non-learning-tree="learningTreeQuestionsInNonLearningTree"
                                :non-learning-tree-questions="nonLearningTreeQuestions"
                                :beta-assignments-exist="betaAssignmentsExist"
                                :h5p-questions-with-anonymous-users="h5pQuestionsWithAnonymousUsers"
                                :h5p-questions-with-real-time-and-multiple-attempts="h5pQuestionsWithRealTimeAndMultipleAttempts"
                                :h5p-non-adapt-questions="h5pNonAdaptQuestions"
                                :pending-question-revisions="pendingQuestionRevisions"
        />
        <div v-show="assessmentType === 'clicker' && solutionsReleased">
          <b-alert show variant="danger">
            The solutions to this assignment have been released. Students have access to the questions and the clicker
            functionality is disabled. If you would like to initiate the clicker assignment, please first hide the
            solutions by visiting the Control Panel.
          </b-alert>
        </div>
        <div v-if="items.length">
          <div v-if="formative">
            <p>
              This is a formative assignment. Students will have access to their submissions during a single
              session.
            </p>
          </div>
          <div v-else>
            <p>
              The assessments that make up this assignment are <span class="font-weight-bold">{{
                assessmentType
              }}</span>
              assessments.
              <span v-if="assessmentType === 'delayed'">
                Students will be able to get feedback for their responses after the assignment is closed.
              </span>
              <span v-if="assessmentType === 'learning tree'">
                Learning trees provide additional resources if they are unable to answer a question correctly.
              </span>
              <span v-if="assessmentType === 'clicker'">
                Students answer questions within a short timeframe and instructors get up-to-date statistics on submissions.
              </span>
            </p>
          </div>
        </div>
        <b-alert :show="assessmentType ==='real time' && hasOpenEndedQuestions" variant="info">
          <p>The questions colored in red below have open-ended components associated with this real time
            assignment. It is recommended
            to only use auto-graded questions in real time assignments.</p>
          <p v-show="items.find(question => question.submission.search(',') !== -1)">Some of your questions have both an
            open-ended component and an
            auto-graded component. You
            can
            <b-button size="sm"
                      variant="info"
                      @click="initRemoveOpenEndedComponent"
            >remove the open-ended component
            </b-button>
            to make them purely auto-graded.
          </p>
        </b-alert>
        <b-card v-show="user.role === 2 && betaCourseApprovals.length"
                header="default"
                header-html="<h2 class=&quot;h7&quot;>Beta Course Approvals</h2>"
        >
          <p>
            The Alpha course instructor has either added or removed assessments on the tethered assignment.
            By approving any changes here, your own students' assignments will reflect the changes.
            In addition, their scores will be automatically updated to reflect the change; it is therefore
            advisable not to approve any changes during a grading period.
          </p>
          <p class="font-weight-bold">
            Due to the tethered nature of the assignment, once you approve an add or remove, this action cannot be
            undone.
          </p>
          <b-card-text>
            <b-table aria-label="Beta course approvals"
                     striped
                     hover
                     :fields="fields"
                     :items="betaCourseApprovals"
            >
              <template v-slot:cell(title)="data">
                <a href="" @click.prevent="viewQuestionInModal(data.item,data.item.action)">
                  {{ data.item.title !== null ? data.item.title : 'None provided' }}
                </a>
              </template>
              <template v-slot:cell(action)="data">
                <b-button v-if="data.item.action === 'add'"
                          variant="primary"
                          size="sm"
                          @click="alphaAssignmentQuestion=data.item;addQuestionFromAlphaAssignment()"
                >
                  Add
                </b-button>
                <b-button v-if="data.item.action === 'remove'"
                          variant="danger"
                          size="sm"
                          @click="alphaAssignmentQuestion=data.item;removeQuestionFromBetaAssignment()"
                >
                  Remove
                </b-button>
              </template>
            </b-table>
          </b-card-text>
        </b-card>
        <div v-if="items.length">
          <b-button v-if="false"
                    variant="primary"
                    size="sm"
                    @click="refreshQuestions('question_properties')"
          >
            Refresh Question Properties
          </b-button>
          <QuestionCircleTooltip v-if="false" :id="'refresh-question-properties'"/>
          <b-tooltip target="refresh-question-properties"
                     delay="500"
                     triggers="hover focus"
          >
            If you have updated the text, solution, a11y, hint, or libretexts link, in any of questions in this
            assignment,
            then you can perform a mass refresh for all of them.
          </b-tooltip>
          <span class="float-right">
            <b-button v-if="false"
                      variant="danger"
                      size="sm"
                      @click="confirmRefreshQuestionsAndProperties"
            >
              Refresh Questions And Properties
            </b-button>
          </span>
        </div>
        <span v-show="false" id="question-ids">{{ currentOrderedQuestions.join(', ') }}</span>
        <table class=" table table-striped mt-2"
               aria-label="Assignment questions"
        >
          <thead>
          <tr>
            <th scope="col" style="width:85px;">
              Order
            </th>
            <th scope="col">
              Title
              <b-icon-sort-alpha-down id="sort-by-title" @click="sortByTitle"/>
            </th>
            <th v-if="user.role === 2 && assessmentType !== 'learning tree'" scope="col" style="width: 150px;">
              ADAPT ID
              <QuestionCircleTooltip :id="'adapt-id-tooltip'"/>
              <b-tooltip target="adapt-id-tooltip"
                         delay="500"
                         triggers="hover focus"
              >
                This ID is of the form {Assignment ID}-{Question ID} and is unique at the assignment level.
              </b-tooltip>
              <a id="copy-all-question-ids-tooltip"
                 href=""
                 class="pr-1"
                 aria-label="Copy all ID's"
                 @click.prevent="doCopy('question-ids')"
              >
                <font-awesome-icon :icon="copyIcon"/>
              </a>
              <b-tooltip target="copy-all-question-ids-tooltip"
                         delay="500"
                         triggers="hover focus"
              >
                Copy all ID's
              </b-tooltip>
            </th>
            <th v-if="user.role === 2 && assessmentType==='learning tree'" scope="col">
              Tree ID
            </th>
            <th v-if="assessmentType !== 'learning tree'" scope="col">
              Submission
            </th>
            <th v-if="!formative" scope="col">
              Points
            </th>
            <th v-if="assessmentType !== 'learning tree'" scope="col">
              Solution
            </th>
            <th v-if="user.role === 2" scope="col"
                :style="(isAdmin && isCommonsCourse) || assessmentType === 'learning tree' ? 'width:115px;' : 'width:90px;'"
            >
              Actions
            </th>
            <th v-if="showRefreshStatus" scope="col">
              Refresh Status
            </th>
          </tr>
          </thead>
          <tbody is="draggable"
                 v-model="items"
                 tag="tbody"
                 :options="{disabled : user.role === 4,handle: '.handle'}"
                 @end="saveNewOrder"
          >
          <tr v-for="item in items"
              :key="item.id"
              :style="assessmentType !== 'delayed' && item.is_open_ended ? 'background-color: #ffcccc;' :''"
          >
            <th scope="row">
              <b-icon v-if="user.role === 2" class="handle" icon="list"/>
              {{ item.order }}
              <b-icon v-if="user.role === 2 && assessmentType !== 'delayed' && item.is_open_ended"
                      :id="`delayed-in-real-time-tooltip-${item.id}`"
                      variant="primary"
                      style="cursor:pointer;"
                      icon="exclamation-triangle"
              />
              <b-tooltip :target="`delayed-in-real-time-tooltip-${item.id}`"
                         delay="500"
                         triggers="hover focus"
              >
                This question is an open-ended question in a real time assignment. These types of questions are
                typically used in delayed assignments.
              </b-tooltip>
            </th>
            <td>
                <span v-show="isBetaAssignment"
                      class="text-muted"
                >&beta; </span>
              <span v-show="isAlphaCourse"
                    class="text-muted"
              >&alpha; </span>
              <b-icon
                v-if="assessmentType === 'learning tree'"
                icon="tree"
                variant="success"
              />
              <a :href="`/assignments/${assignmentId}/questions/view/${item.question_id}/view`">{{ item.title }}</a>
              <CustomTitle
                :assignment-id="+assignmentId"
                :question-id="item.question_id"
                :title="item.title"
                @updateCustomQuestionTitle="updateCustomQuestionTitle"
              />
              <AlgorithmicIcon :algorithmic-question="item.algorithmic"
                               :algorithmic-assignment="isAlgorithmicAssignment"
              />
              <FormativeWarning :formative-question="item.is_formative_question"
                                :question-id="item.question_id"
              />

              <span v-html="item.migrationMessage"/>
            </td>
            <td v-if="user.role === 2 && assessmentType !== 'learning tree'">
                <span :id="`assignment-question-${item.assignment_id_question_id}`">{{
                    item.assignment_id_question_id
                  }}</span>
              <b-tooltip :target="getTooltipTarget('copy',item.question_id)"
                         delay="500"
                         triggers="hover focus"
              >
                Copy ADAPT ID {{ item.question_id }}
              </b-tooltip>
              <a :id="getTooltipTarget('copy',item.question_id)"
                 href=""
                 class="pr-1"
                 :aria-label="`Copy ADAPT ID for ${item.title}`"
                 @click.prevent="doCopy(`assignment-question-${item.assignment_id_question_id}`)"
              >
                <font-awesome-icon :icon="copyIcon"/>
              </a>
            </td>

            <td v-if="user.role === 2 && assessmentType==='learning tree'">
              <span v-if="item.learning_tree_user_id !== user.id">{{ item.learning_tree_id }}</span>
              <span v-if="item.learning_tree_user_id === user.id">
                  <a :href="`/instructors/learning-trees/editor/${item.learning_tree_id}`" target="_blank"
                  >{{ item.learning_tree_id }}</a>
                </span>
            </td>
            <td v-if="assessmentType !== 'learning tree'">
              {{ item.submission }}
            </td>
            <td v-if="!formative">
              {{ item.points }}
            </td>
            <td v-if="assessmentType !== 'learning tree'">
                <span v-if="item.qti_answer_json && !isDiscussIt(item) && !isFlashcard(item)">
                  <span
                    v-if="isQtiOrForgeWithQtiAnswerSolution(item)"
                  >
                    <QtiJsonAnswerViewer
                      :modal-id="item.id"
                      :qti-json="item.qti_answer_json"
                    />
                    <b-button
                      size="sm"
                      variant="outline-info"
                      @click="$bvModal.show(`qti-answer-${item.id}`)"
                    >
                      View Correct Answer
                    </b-button>
                  </span>
                   <span
                     v-if="!isQtiOrForgeWithQtiAnswerSolution(item)"
                   >N/A
                   </span>
                </span>
              <span
                v-if="isDiscussIt(item) || isFlashcard(item)"
              >
                  N/A
                </span>
              <SolutionFileHtml v-if="!item.qti_answer_json"
                                :key="`solution-file-html-${item.question_id}`"
                                :questions="items"
                                :current-page="item.order"
                                :format-filename="false"
                                :modal-id="`solution-file-html-${uniqueId()}`"
              />
            </td>
            <td v-if="user.role === 2">

              <b-tooltip :target="getTooltipTarget('edit',item.question_id)"
                         delay="500"
                         triggers="hover focus"
              >
  <span v-if="item.forge_source_id">
    Edit the main Forge question ({{ item.forge_source_id }}). To edit draft-specific settings (title, due dates), you can do so at the usage level.
  </span>
                <span v-else>
    Edit question source
  </span>
              </b-tooltip>
              <span v-if="assessmentType === 'learning tree'">
                  <b-tooltip :target="getTooltipTarget('learningTreeProperties',item.learning_tree_id)"
                             triggers="hover"
                             delay="500"
                  >
                    Tree Properties
                  </b-tooltip>
                  <a :id="getTooltipTarget('learningTreeProperties',item.learning_tree_id)"
                     href="#"
                     class="pr-1"
                     @click="editLearningTreeProperties(item)"
                  >
                    <b-icon class="text-muted"
                            icon="gear"
                            :aria-label="`Tree properties for ${item.title}`"
                    />
                  </a>
                </span>
              <a v-if="assessmentType !== 'learning tree'"
                 :id="getTooltipTarget('edit',item.question_id)"
                 href=""
                 class="pr-1"
                 @click.prevent="editQuestionSource(item)"
              >
                <b-icon class="text-muted"
                        icon="pencil"
                        aria-label="Edit question source"
                />
              </a>
              <span v-if="assessmentType === 'learning tree'">
                  <a
                    :id="`edit-learning-tree-tooltip-${item.learning_tree_id}`"
                    class="p-1"
                    href=""
                    @click.prevent="editLearningTree(item.learning_tree_id)"
                  >
                    <b-icon icon="pencil"
                            aria-label="Edit Learning Tree"
                            class="text-muted"
                            scale="1.1"
                    />
                  </a>
                  <b-tooltip
                    :target="`edit-learning-tree-tooltip-${item.learning_tree_id}`"
                    delay="750"
                    triggers="hover"
                  >    {{ canEditLearningTreeMessage(item) }}
                  </b-tooltip>
                </span>
              <CloneQuestion
                :key="`copy-question-${item.question_id}`"
                :question-id="item.forge_source_id || item.question_id"
                :is-forge-draft="!!item.forge_source_id"
                :element-id="item.question_id"
                :question-editor-user-id="item.question_editor_user_id"
                :title="item.title"
                :license="item.license"
                :public="item.public"
                :library="item.library"
                :learning-tree-id="item.learning_tree_id ? item.learning_tree_id : 0"
                :non-technology="item.non_technology"
                :assignment-id="+assignmentId"
                @reloadQuestions="getAssignmentInfo()"
              />
              <b-tooltip :target="getTooltipTarget('remove',item.question_id)"
                         delay="500"
                         triggers="hover focus"
              >
                <div v-if="['forge','forge_iteration'].includes(item.qti_json_type)">
                <span v-if="item.forge_source_id">
                 Drafts can be removed in the Forge Settings in the questions view
                </span>
                  <span v-else>
                   {{ getRemoveForgeQuestionMessage(item) }}
                  </span>
                </div>
                <div v-else>
                  Remove the {{ assessmentType === 'learning tree' ? 'learning tree' : 'question' }} from the assignment
                </div>
              </b-tooltip>
              <a :id="getTooltipTarget('remove',item.question_id)"
                 href=""
                 class="pr-1"
                 @click.prevent="initRemoveQuestionFromAssignment(item)"
              >
                <b-icon class="text-muted"
                        icon="trash"
                        :aria-label="`Remove question ${item.title} from the assignment`"
                />
              </a>
            </td>
            <td v-if="showRefreshStatus">
              <span v-html="item.refresh_status"/>
            </td>
          </tr>
          </tbody>
        </table>
      </div>
    </div>
    <div v-if="!items.length && !isLoading" class="mt-5">
      <b-alert variant="warning" :show="true">
        <span class="font-weight-bold">This assignment doesn't have any questions.</span>
        <strong/>
      </b-alert>
    </div>
  </div>
</template>
<script>
import axios from 'axios'
import Loading from 'vue-loading-overlay'
import 'vue-loading-overlay/dist/vue-loading.css'
import { mapGetters } from 'vuex'
import draggable from 'vuedraggable'
import { h5pResizer } from '~/helpers/H5PResizer'

import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faCopy } from '@fortawesome/free-regular-svg-icons'

import RemoveQuestion from '~/components/RemoveQuestion'
import { getTooltipTarget, initTooltips } from '~/helpers/Tooptips'
import {
  viewQuestion,
  editQuestionSource,
  getQuestionToEdit,
  getQuestionRevisionToEdit
} from '~/helpers/Questions'
import { doCopy } from '~/helpers/Copy'
import AssessmentTypeWarnings from '~/components/AssessmentTypeWarnings'
import CannotDeleteAssessmentFromBetaAssignmentModal from '~/components/CannotDeleteAssessmentFromBetaAssignmentModal'
import CreateQuestion from '~/components/questions/CreateQuestion'
import QtiJsonAnswerViewer from '~/components/QtiJsonAnswerViewer'
import {
  h5pText,
  updateLearningTreeInNonLearningTreeMessage,
  updateNonLearningTreeInLearningTreeMessage,
  updateH5pNonAdaptQuestionsMessage,
  updatePendingQuestionRevisionsMessage
} from '~/helpers/AssessmentTypeWarnings'
import SolutionFileHtml from '~/components/SolutionFileHtml'
import CloneQuestion from '~/components/CloneQuestion'
import FormativeWarning from '~/components/FormativeWarning.vue'
import CustomTitle from '../../../components/CustomTitle.vue'
import uniqueId from 'vue-select/src/utility/uniqueId'
import Form from 'vform'
import LearningTreeProperties from '~/components/LearningTreeProperties.vue'
import AllFormErrors from '~/components/AllFormErrors.vue'
import { isMobile } from '~/helpers/mobileCheck'
import AlgorithmicIcon from '~/components/AlgorithmicIcon.vue'
import { isQtiOrForgeWithQtiAnswerSolution } from '../../../helpers/Questions'

export default {
  middleware: 'auth',
  components: {
    AlgorithmicIcon,
    AllFormErrors,
    LearningTreeProperties,
    CustomTitle,
    FormativeWarning,
    QtiJsonAnswerViewer,
    AssessmentTypeWarnings,
    FontAwesomeIcon,
    Loading,
    draggable,
    RemoveQuestion,
    CannotDeleteAssessmentFromBetaAssignmentModal,
    SolutionFileHtml,
    CreateQuestion,
    CloneQuestion
  },
  metaInfo () {
    return { title: 'Assignment Questions' }
  },
  data: () => ({
    openEndedComponentsToRemoveFields: ['order', 'title', 'status'],
    openEndedComponentsToRemove: [],
    hasOpenEndedQuestions: false,
    showResetOpenEndedButton: false,
    isAlgorithmicAssignment: false,
    canEditLearningTree: false,
    allFormErrors: [],
    learningTreeId: 0,
    learningTreeForm: new Form(),
    pendingQuestionRevisions: '',
    formative: false,
    isCommonsCourse: false,
    assignmentId: 0,
    migrateToAdaptQuestionId: 0,
    migrateToAdaptQuestionTitle: '',
    migrateToAdaptAssignmentId: 0,
    h5pNonAdaptQuestions: [],
    isQuestionWeight: false,
    submissionsExist: false,
    h5pQuestionsWithRealTimeAndMultipleAttempts: false,
    confirmedRefreshQuestionsAndProperties: false,
    showRefreshStatus: false,
    h5pQuestionsWithAnonymousUsers: false,
    isAlphaCourse: false,
    viewQuestionAction: '',
    alphaAssignmentQuestion: {},
    fields: [
      'title',
      'action'
    ],
    betaCourseApprovals: [],
    isBetaAssignment: false,
    betaAssignmentsExist: false,
    openEndedQuestionsInRealTime: '',
    learningTreeQuestionsInNonLearningTree: '',
    nonLearningTreeQuestions: '',
    assessmentType: '',
    solutionsReleased: false,
    adaptId: 0,
    copyIcon: faCopy,
    currentOrderedQuestions: [],
    items: [],
    isLoading: true,
    questionId: 0,
    questionToEdit: {}
  }),
  computed: {
    ...mapGetters({
      user: 'auth/user'
    }),
    isAdmin: () => window.config.isAdmin
  },
  created () {
    this.updateLearningTreeInNonLearningTreeMessage = updateLearningTreeInNonLearningTreeMessage
    this.updateNonLearningTreeInLearningTreeMessage = updateNonLearningTreeInLearningTreeMessage
    this.updateH5pNonAdaptQuestionsMessage = updateH5pNonAdaptQuestionsMessage
    this.updatePendingQuestionRevisionsMessage = updatePendingQuestionRevisionsMessage
    this.h5pText = h5pText
    this.getQuestionToEdit = getQuestionToEdit
    this.getQuestionRevisionToEdit = getQuestionRevisionToEdit
  },
  mounted () {
    if (![2, 4, 5].includes(this.user.role)) {
      this.$router.push({ name: 'no.access' })
      return false
    }
    this.viewQuestion = viewQuestion
    initTooltips(this)
    this.assignmentId = this.$route.params.assignmentId
    this.getAssignmentInfo()
    this.getBetaCourseApprovals()
    h5pResizer()
  },
  methods: {
    doCopy,
    getTooltipTarget,
    isMobile,
    editQuestionSource,
    isQtiOrForgeWithQtiAnswerSolution,
    getRemoveForgeQuestionMessage (question) {
      return this.forgeDraftExists(question) ? 'Please first remove all drafts from the Forge Settings within the assignment.' : 'Remove question from assignment.'
    },
    forgeDraftExists (question) {
      const parentForgeQuestionId = question.id
      return this.items.filter(
        question => question.forge_source_id === parentForgeQuestionId
      ).length > 0
    },
    uniqueId,
    async removeOpenEndedComponents () {
      for (let i = 0; i < this.openEndedComponentsToRemove.length; i++) {
        const question = this.openEndedComponentsToRemove[i]
        try {
          const { data } = await axios.patch(`/api/assignments/${this.assignmentId}/questions/${question.question_id}/update-open-ended-submission-type`, { 'open_ended_submission_type': '0' })
          const status = data.type === 'success' ? 'Updated' : data.message
          this.$set(this.openEndedComponentsToRemove[i], 'status', status)
        } catch (error) {
          this.$set(this.openEndedComponentsToRemove[i], 'status', error.message)
        }
      }
      await this.getAssignmentInfo()
    },
    initRemoveOpenEndedComponent () {
      this.openEndedComponentsToRemove = this.items.filter(item => item.submission.search(',') !== -1)
      for (let i = 0; i < this.openEndedComponentsToRemove.length; i++) {
        this.$set(this.openEndedComponentsToRemove[i], 'status', 'Pending')
      }
      this.$bvModal.show('modal-confirm-remove-open-ended-component')
    },
    isDiscussIt (question) {
      try {
        return JSON.parse(question.qti_answer_json).questionType === 'discuss_it'
      } catch (error) {

      }
      return false
    },
    isFlashcard (question) {
      try {
        return JSON.parse(question.qti_answer_json).questionType === 'flashcard'
      } catch (error) {

      }
      return false
    },
    canEditLearningTreeMessage (item) {
      return item.learning_tree_can_edit
        ? 'Edit the learning tree'
        : 'Since you do not own this learning tree you may view it but not edit it.'
    },
    async saveLearningTreeProperties () {
      try {
        const { data } = await this.learningTreeForm.post(`/api/learning-trees/info/${this.learningTreeId}`)
        this.$noty[data.type](data.message)
        await this.getAssignmentInfo()
        this.$bvModal.hide('modal-learning-tree-properties')
      } catch (error) {
        if (!error.message.includes('status code 422')) {
          this.$noty.error(error.message)
        } else {
          this.allFormErrors = this.learningTreeForm.errors.flatten()
          this.$bvModal.show('modal-form-errors-learning-tree-questions')
        }
      }
    },
    editLearningTreeProperties (learningTree) {
      this.learningTreeId = learningTree.learning_tree_id
      this.canEditLearningTree = learningTree.learning_tree_can_edit
      this.learningTreeForm = new Form({
        title: learningTree.title,
        description: learningTree.learning_tree_description,
        public: learningTree.learning_tree_public,
        notes: learningTree.learning_tree_notes
      })
      console.log(this.learningTreeForm)
      this.$bvModal.show('modal-learning-tree-properties')
    },
    editLearningTree (learningTreeId) {
      localStorage.setItem('learning_tree_editor_referrer', this.$route.name)
      window.open(`/instructors/learning-trees/editor/${learningTreeId}`, '_blank')
    },
    setQuestionRevision (revision) {
      console.log('setting revision')
      console.log(this.questionToEdit)
      this.getQuestionRevisionToEdit(revision)
    },
    updateCustomQuestionTitle () {
      this.getAssignmentInfo()
    },
    updateMigrationMessage (questionId, type, message) {
      let migratedQuestion = this.items.find(question => question.id === questionId)
      if (migratedQuestion) {
        let messageClass = type === 'success' ? 'text-success' : 'text-danger'
        migratedQuestion.migrationMessage = `<span class="${messageClass}">${message}</span>`
      }
      this.$forceUpdate()
    },
    initRemoveQuestionFromAssignment (question) {
      if (this.forgeDraftExists(question)) {
        this.$noty.info('Please first remove all drafts by opening the Forge Settings within the assignment.')
        return
      }
      if (question.forge_source_id) {
        this.$noty.info('You can delete this draft by opening up the Forge Settings in the questions view.')
        return
      }
      const questionId = question.id
      this.submissionsExist && this.isQuestionWeight
        ? this.$noty.info('You cannot remove this question since there are already submissions and this assignment computes points using question weights.')
        : this.openRemoveQuestionModal(questionId)
    },
    confirmRefreshQuestionsAndProperties () {
      this.confirmedRefreshQuestionsAndProperties = false
      this.$bvModal.show('modal-confirm-refresh-questions-and-properties')
    },
    async refreshQuestions (type) {
      if (type === 'questions_and_properties') {
        if (!this.confirmedRefreshQuestionsAndProperties) {
          this.$noty.info('Please check the box before proceeding.')
          return false
        }
        this.$bvModal.hide('modal-confirm-refresh-questions-and-properties')
      }
      let url
      let method
      for (let i = 0; i < this.items.length; i++) {
        this.items[i].refresh_status = 'Pending'
      }
      this.showRefreshStatus = true
      for (let i = 0; i < this.items.length; i++) {
        try {
          url = (type === 'question_properties')
            ? `/api/questions/${this.items[i].question_id}/refresh-properties`
            : `/api/questions/${this.items[i].question_id}/refresh/${this.assignmentId}`
          method = (type === 'question_properties')
            ? 'patch'
            : 'post'
          const { data } = await axios[method](url)
          this.items[i].refresh_status = data.type === 'success'
            ? '<span class="text-success">Success</span>'
            : '<span class="text-danger">Error</span>'
          if (data.type === 'success' && data.solution_html) {
            this.items[i].solution_html = data.solution_html
            this.items[i].solution_type = 'html'
          }
        } catch (error) {
          this.items[i].refresh_status = '<span class="text-danger">Error</span>'
        }
        this.$forceUpdate()
      }
    },
    viewQuestionInModal (question, action) {
      this.alphaAssignmentQuestion = question
      this.viewQuestionAction = action
      this.$bvModal.show('modal-view-question')
    },
    async removeQuestionFromBetaAssignment () {
      try {
        const { data } = await axios.delete(`/api/assignments/${this.assignmentId}/questions/${this.alphaAssignmentQuestion.question_id}`)
        this.$noty[data.type](data.message)
        if (data.type !== 'error') {
          this.betaCourseApprovals = this.betaCourseApprovals.filter(question => question.question_id !== this.alphaAssignmentQuestion.question_id)
          await this.getAssignmentInfo()
          this.$bvModal.hide('modal-view-question')
        }
      } catch (error) {
        this.$noty.error('We could not remove the question from the assignment.  Please try again or contact us for assistance.')
      }
    },

    async addQuestionFromAlphaAssignment () {
      try {
        const { data } = await axios.post(`/api/assignments/${this.assignmentId}/questions/${this.alphaAssignmentQuestion.question_id}`)
        this.$noty[data.type](data.message)
        if (data.type === 'success') {
          this.betaCourseApprovals = this.betaCourseApprovals.filter(question => question.question_id !== this.alphaAssignmentQuestion.question_id)
          await this.getAssignmentInfo()
          this.$bvModal.hide('modal-view-question')
        }
      } catch (error) {
        this.$noty.error('We could not add the question to the assignment.  Please try again or contact us for assistance.')
      }
    },
    async getBetaCourseApprovals () {
      try {
        const { data } = await axios.get(`/api/beta-course-approvals/assignment/${this.assignmentId}`)
        if (data.type === 'error') {
          this.$noty.error(data.message)
          return false
        }
        this.betaCourseApprovals = data.beta_course_approvals
      } catch (error) {
        this.$noty.error('We could not retrieve your Beta course approvals.  Please try again or contact us for assistance.')
      }
    },
    async submitRemoveQuestion () {
      try {
        const { data } = await axios.delete(`/api/assignments/${this.assignmentId}/questions/${this.questionId}`)
        this.$bvModal.hide('modal-remove-question')
        if (data.type === 'error') {
          this.$noty.error(data.message)
          return false
        }
        this.$noty.info(data.message)
        await this.getAssignmentInfo()
      } catch (error) {
        this.$noty.error('We could not remove the question from the assignment.  Please try again or contact us for assistance.')
      }
    },
    openRemoveQuestionModal (questionId) {
      if (this.isBetaAssignment) {
        this.$bvModal.show('modal-cannot-delete-assessment-from-beta-assignment')
        return false
      }
      this.questionId = questionId
      this.$bvModal.show('modal-remove-question')
    },
    async sortByTitle () {
      this.items.sort(function (a, b) {
        if (a.title < b.title) return -1
        if (a.title > b.title) return 1
        return 0
      })
      for (let i = 0; i < this.items.length; i++) {
        this.items[i].order = i + 1
      }
      await this.saveNewOrder()
    },
    async saveNewOrder () {
      let orderedQuestions = []
      for (let i = 0; i < this.items.length; i++) {
        orderedQuestions.push(this.items[i].question_id)
      }

      let noChange = true
      for (let i = 0; i < this.currentOrderedQuestions.length; i++) {
        if (this.currentOrderedQuestions[i] !== this.items[i]) {
          noChange = false
        }
      }
      if (noChange) {
        return false
      }
      try {
        const { data } = await axios.patch(`/api/assignments/${this.assignmentId}/questions/order`, { ordered_questions: orderedQuestions })
        this.$noty[data.type](data.message)
        if (data.type === 'success') {
          for (let i = 0; i < this.items.length; i++) {
            this.items[i].order = i + 1
          }
          this.currentOrderedQuestions = this.items
        }
      } catch (error) {
        this.$noty.error(error.message)
      }
      this.isLoading = false
    },
    async getAssignmentInfo () {
      try {
        const { data } = await axios.get(`/api/assignments/${this.assignmentId}/questions/summary`)
        this.isLoading = false
        if (data.type === 'error') {
          this.$noty.error(data.message)
          return false
        }
        this.hasOpenEndedQuestions = data.has_open_ended_questions
        this.showResetOpenEndedButton = data.show_reset_open_ended_button
        this.formative = data.formative
        this.submissionsExist = data.submissions_exist
        this.assessmentType = data.assessment_type
        this.isAlgorithmicAssignment = Boolean(data.is_algorithmic_assignment)
        this.betaAssignmentsExist = data.beta_assignments_exist
        this.isBetaAssignment = data.is_beta_assignment
        this.isAlphaCourse = data.is_alpha_course
        this.isCommonsCourse = data.is_commons_course
        this.isQuestionWeight = data.is_question_weight
        this.solutionsReleased = data.solutions_released
        this.h5pQuestionsWithAnonymousUsers = data.h5p_questions_exist && data.course_has_anonymous_users
        this.h5pQuestionsWithRealTimeAndMultipleAttempts = data.h5p_questions_exist && data.real_time_with_multiple_attempts
        this.items = data.rows
        let hasNonH5P
        for (let i = 0; i < this.items.length; i++) {
          this.items[i].migrationMessage = ''
          if (this.items[i].submission !== 'h5p') {
            hasNonH5P = true
          }
          if (this.assessmentType !== 'delayed' && !this.items[i].auto_graded_only) {
            this.openEndedQuestionsInRealTime += this.items[i].order + ', '
          }
          this.currentOrderedQuestions.push(this.items[i].question_id)
        }

        console.log(data)
        this.updateH5pNonAdaptQuestionsMessage()
        this.updatePendingQuestionRevisionsMessage()
        this.updateLearningTreeInNonLearningTreeMessage()
        this.updateNonLearningTreeInLearningTreeMessage()

        if (this.assessment_type === 'clicker' && hasNonH5P) {
          this.$bvModal.show('modal-non-h5p')
        }
      } catch (error) {
        this.$noty.error(error.message)
      }
      this.isLoading = false
    }
  }
}
</script>
