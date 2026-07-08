<template>
  <div :style="!inIFrame ? '' : 'margin-bottom:10px;'">
    <div v-if="questions[currentPage-1]" id="questions-loaded"/>
    <AllFormErrors :all-form-errors="allFormErrors" :modal-id="'modal-form-errors-completion-scoring-mode'"/>
    <AllFormErrors :all-form-errors="allFormErrors" :modal-id="'modal-form-errors-libretexts-solution-error-form'"/>
    <AllFormErrors :all-form-errors="allFormErrors" :modal-id="'modal-form-errors-file-upload'"/>
    <AllFormErrors :all-form-errors="allFormErrors" :modal-id="'modal-form-errors-clicker-time-to-submit-error-for'"/>
    <AllFormErrors :all-form-errors="allFormErrors"
                   :modal-id="'modal-form-errors-assignment-question-learning-tree-info'"
    />
    <div v-if="questions[currentPage-1]">
      <b-modal
        :id="`modal-save-to-my-favorites`"
        ref="saveToMyFavorites"
        title="Save Question To My Favorites"
      >
        <SavedQuestionsFolders
          ref="savedQuestionsFolders"
          :type="'my_favorites'"
          :init-saved-questions-folder="myFavoritesFolder"
          :create-modal-add-saved-questions-folder="true"
          :question-source-is-my-favorites="false"
          @savedQuestionsFolderSet="setMyFavoritesFolder"
        />
        <template #modal-footer>
          <b-button
            size="sm"
            class="float-right"
            @click="$bvModal.hide(`modal-save-to-my-favorites`)"
          >
            Cancel
          </b-button>
          <b-button
            variant="primary"
            size="sm"
            class="float-right"
            @click="addQuestionToFavorites('single')"
          >
            Submit
          </b-button>
        </template>
      </b-modal>
      <b-modal id="modal-submission-information"
               :title="modalSubmissionInformationTitle"
      >
        <b-card v-if="showAutoGradedSubmissionInformation"
                :class="{'no-card-style': !showOpenEndedSubmissionInformation}"
                :header-html="autoGradedSubmissionInformationHeader"
        >
          <b-card-text>
            <ul style="list-style-type:none" class="pl-0">
              <li v-if="!['qti','webwork','imathas'].includes(questions[currentPage-1].technology)">
                <span class="font-weight-bold pr-1">Submission
                  <span v-if="!questions[currentPage - 1].has_h5p_activity_set_submissions">
                    <span
                      :class="{ 'text-danger': questions[currentPage - 1].last_submitted === 'N/A' }"
                    >{{
                        questions[currentPage - 1].student_response
                      }}</span>
                  </span>
                </span>
                <span v-if="questions[currentPage - 1].has_h5p_activity_set_submissions">
                  <b-button size="sm" variant="primary"
                            @click="$bvModal.show('modal-h5p-activity-set-submissions')"
                  >View</b-button>
                </span>
              </li>
              <li
                v-if="(questions[currentPage-1].qti_json_type === 'marker' && questions[currentPage-1].qti_answer_json) || (['webwork','imathas'].includes(questions[currentPage-1].technology) && submissionArray.length)"
                class="flex d-inline-flex"
              >
                <span class="font-weight-bold pr-1">Submission:
                  <span v-if="questions[currentPage - 1].last_submitted === 'N/A'" class="text-danger">N/A</span>
                </span>
                <span v-if="questions[currentPage - 1].last_submitted !== 'N/A'">
                  <b-button size="sm" variant="info" @click="showSubmissionArray">View Summary</b-button></span>
              </li>
              <li>
                <span class="font-weight-bold">Submitted At:
                  <span
                    :class="{ 'text-danger': questions[currentPage - 1].last_submitted === 'N/A' }"
                  >{{
                      questions[currentPage - 1].last_submitted
                    }} </span>
                </span>
              </li>
              <li v-if="showScores">
                <span class="font-weight-bold">Score: {{
                    questions[currentPage - 1].submission_score
                  }}</span>
              </li>
              <li v-if="showScores">
                <strong>Z-Score: {{ questions[currentPage - 1].submission_z_score }}</strong> <br>
              </li>
              <li v-if="parseFloat(questions[currentPage - 1].late_penalty_percent) > 0 && showScores">
                <span class="font-weight-bold">Late Penalty: {{
                    questions[currentPage - 1].late_penalty_percent
                  }}%</span>
              </li>
            </ul>
          </b-card-text>
        </b-card>
        <b-card v-if="showOpenEndedSubmissionInformation"
                :class="{'no-card-style': !showAutoGradedSubmissionInformation, 'mt-3':showAutoGradedSubmissionInformation }"
                :header-html="openEndedSubmissionInformationHeader"
        >
          <b-card-text>
            <ul style="list-style-type:none" class="pl-0">
              <li v-if="!isForge() && (isOpenEndedFileSubmission || isOpenEndedAudioSubmission)">
                <strong> Uploaded file:
                  <span v-if="questions[currentPage-1].submission_file_exists">
                    <a
                      :href="questions[currentPage-1].submission_file_url"
                      target="”_blank”"
                    >
                      View Submission
                    </a>
                  </span>
                  <span v-if="!questions[currentPage-1].submission_file_exists" class="text-danger">
                    No files have been uploaded.</span>
                </strong>
              </li>
              <li>
                <strong>Submitted At:
                  <span
                    :class="{ 'text-danger': questions[currentPage - 1].date_submitted === 'N/A' }"
                  >{{ questions[currentPage - 1].date_submitted }}</span></strong>
              </li>
              <li v-if="showScores">
                <strong>Date Graded: {{ questions[currentPage - 1].date_graded }}</strong>
              </li>

              <li v-if="showScores && questions[currentPage-1].file_feedback">
                <strong>{{ capitalize(questions[currentPage - 1].file_feedback_type) }} Feedback:
                  <a :href="questions[currentPage-1].file_feedback_url"
                     target="”_blank”"
                  >
                    {{
                      questions[currentPage - 1].file_feedback_type === 'audio' ? 'Listen To Feedback' : 'View Feedback'
                    }}
                  </a>
                </strong>
              </li>
              <li v-if="!showScores">
                <RubricPointsBreakdownModal
                  v-if="questions[currentPage-1].rubric"
                  :key="`rubric-points-modal-${questions[currentPage - 1].id}`"
                  :user-id="user.id"
                  :assignment-id="+assignmentId"
                  :rubric="questions[currentPage-1].rubric"
                  :question-id="questions[currentPage-1].id"
                  :question-points="+questions[currentPage - 1].points"
                />
              </li>
              <li v-if="showScores">
                <strong>Comments:
                  <span v-if="questions[currentPage - 1].text_feedback"
                        v-html="questions[currentPage - 1].text_feedback"
                  />
                  <span v-if="!questions[currentPage - 1].text_feedback">None Provided.</span>
                </strong>
              </li>
              <li v-if="showScores">
                <strong>Score: {{ questions[currentPage - 1].submission_file_score }}</strong><span
                class="pl-1"
              >
                  <RubricPointsBreakdownModal
                    v-if="questions[currentPage-1].rubric"
                    :key="`rubric-points-modal-${questions[currentPage - 1].id}`"
                    :user-id="user.id"
                    :assignment-id="+assignmentId"
                    :rubric="questions[currentPage-1].rubric"
                    :question-id="questions[currentPage-1].id"
                    :question-points="+questions[currentPage - 1].points"
                  />
                </span>
              </li>
              <li v-if="questions[currentPage - 1].submission_file_late_penalty_percent">
                <span class="font-weight-bold">Late Penalty: {{
                    questions[currentPage - 1].submission_file_late_penalty_percent
                  }}%</span>
              </li>
              <li v-if="showScores">
                <strong>Z-Score: {{ questions[currentPage - 1].submission_file_z_score }}</strong>
              </li>
            </ul>
            <b-alert :variant="openEndedSubmissionDataType" :show="showOpenEndedSubmissionMessage">
              <span class="font-weight-bold">{{ openEndedSubmissionDataMessage }}</span>
            </b-alert>
          </b-card-text>
        </b-card>
        <template #modal-footer>
          <b-button
            variant="primary"
            size="sm"
            @click="$bvModal.hide('modal-submission-information')"
          >
            OK
          </b-button>
        </template>
      </b-modal>
    </div>
    <b-modal id="modal-confirm-paste-into-ckeditor"
             title="Pasted Content"
             no-close-on-esc
             no-close-on-backdrop
             hide-header-close
    >
      <p>
        You have just pasted content for your text submission. We will tag your submission
        so that your instructor is aware of this.
      </p>
      <p>Alternatively, you may start the process again and type in your response directly.</p>
      <template #modal-footer>
        <span class="mr-2">
          <b-button
            variant="primary"
            size="sm"
            @click="keepPastedContent"
          >
            Keep Pasted Content
          </b-button>
        </span>
        <b-button
          size="sm"
          variant="outline-danger"
          @click="startAgain"
        >
          Start Again
        </b-button>
      </template>
    </b-modal>
    <b-modal id="modal-confirm-update-use-existing-cubric"
             title="Confirm Switching Rubric"
    >
      <b-alert :show="rubricPointsBreakDownExists" variant="danger">
        You have already assigned rubric points for at least one student for this question. All rubric points breakdown
        for this assignment question
        will be deleted.
      </b-alert>
      <template #modal-footer>
        <b-button
          size="sm"
          @click="$bvModal.hide('modal-confirm-delete-overriding-rubric')"
        >
          Cancel
        </b-button>
        <b-button
          size="sm"
          variant="danger"
          @click="updateUseExistingRubric()"
        >
          Switch Rubric
        </b-button>
      </template>
    </b-modal>
    <b-modal id="modal-confirm-delete-overriding-rubric"
             title="Confirm Delete Overriding Rubric"
    >
      <p>Please confirm that you would like to delete the overriding rubric. This action cannot be undone.</p>
      <b-alert :show="rubricPointsBreakDownExists" variant="danger">
        You have already assigned rubric points for at least one student for this question. All rubric points breakdown
        for this assignment question
        will be deleted.
      </b-alert>
      <template #modal-footer>
        <b-button
          size="sm"
          @click="$bvModal.hide('modal-confirm-delete-overriding-rubric')"
        >
          Cancel
        </b-button>
        <b-button
          size="sm"
          variant="danger"
          @click="deleteOverridingRubric()"
        >
          Delete
        </b-button>
      </template>
    </b-modal>
    <b-modal id="modal-no-assignment-and-grades-url"
             title="Not Linked to LMS"
             hide-footer
             no-close-on-esc
             no-close-on-backdrop
             hide-header-close
    >
      This assignment may be entered through your LMS or through the ADAPT website. However, the assignment has not been
      linked to your LMS yet.
      Please contact your instructor and ask them to link the assignment.
    </b-modal>
    <b-modal id="modal-question-statistics"
             title="Question Statistics"
             size="lg"
    >
      <div v-if="isDiscussIt()">
        <b-container>
          <b-row>
            <b-col>
              <b-card header="default" header-html="<span class=&quot;font-weight-bold&quot;>Question Statistics</span>"
                      class="mb-2"
              >
                <b-card-text>
                  <ul>
                    <li>{{ scores.length }} scored student submissions</li>
                    <li v-if="scores.length">
                      Maximum score of {{ max }}
                    </li>
                    <li v-if="scores.length">
                      Minimum score of {{ min }}
                    </li>
                    <li v-if="scores.length">
                      Mean score of {{ mean }}
                    </li>
                    <li v-if="scores.length">
                      Standard deviation of {{ stdev }}
                    </li>
                  </ul>
                </b-card-text>
              </b-card>
            </b-col>
            <b-col>
              <HistogramAndTableView :chartdata="chartdata"/>
            </b-col>
          </b-row>
        </b-container>
      </div>
      <template #modal-footer="{ ok}">
        <b-button
          size="sm"
          variant="primary"
          @click="$bvModal.hide('modal-question-statistics')"
        >
          OK
        </b-button>
      </template>
    </b-modal>
    <b-modal id="modal-instructor-clicker-question"
             no-close-on-backdrop
             size="huge"
             footer-class="d-block"
             @hidden="modalInstructorClickerQuestionShown = false;selectedClickerViewOption='question'"
    >
      <template #modal-header>
        <div class="d-flex justify-content-center w-100">
          <div v-show="!canViewClickerSubmissions" class="text-center">
            <div v-if="!clickerPaused" class="timer-wrapper">
              <countdown
                ref="instructorCountdown"
                :key="`countdown-key-${countDownKey}`"
                :time="timeLeft"
                @end="endClickerAssessment"
              >
                <template v-slot="props">
                  <span
                    v-if="props.seconds"
                    style="font-size: xx-large; font-weight: bold"
                    class="d-block mx-auto"
                    :class="props.seconds < 11 ? 'text-danger' : ''"
                    v-html="getTimeLeftMessage(props, assessmentType)"
                  />
                </template>
                {{ timeLeft }}
              </countdown>
            </div>

            <span v-if="clickerPaused" class="d-block mx-auto font-weight-bold"
                  style="font-size:xx-large"
            >
              The timer is currently paused...
            </span>
          </div>
          <b-form-radio-group
            v-model="selectedClickerViewOption"
            name="clicker-view-options"
            plain
          >
            <label v-for="option in clickerViewOptions"
                   v-show="showClickerViewOption(canViewClickerSubmissions, questions[currentPage-1], option.value)"
                   :key="option.value"
                   class="radio-label"
            >
              <input
                v-model="selectedClickerViewOption"
                type="radio"
                :value="option.value"
                class="big-radio"
                @input="updateSelectedClickerViewOption"
              >
              {{ option.text }}
            </label>
          </b-form-radio-group>
        </div>
        <span style="margin-top:-10px">
          <b-button
            variant="outline-default"
            class="pb-1 p-0  big-close float-right"
            aria-label="Close"
            @click="closeInstructorClickerQuestionModal()"
          >
            &times;
          </b-button>
        </span>
      </template>
      <div v-show="selectedClickerViewOption === 'solution'"
           v-if="questions[currentPage - 1] && questions[currentPage - 1].solution_html"
      >
        <div
          v-html="questions[currentPage - 1].solution_html !== null ? questions[currentPage - 1].solution_html.replace(solutionHtmlHeader, '') : ''"
        />
      </div>
      <div v-if="canViewClickerSubmissions && clickerViewIsSubmissions">
        <div v-show="!['solution','question'].includes(selectedClickerViewOption)"
             style="font-size: 20px; white-space: nowrap;"
             class="text-center"
        >
          {{ responsePercent }}% of students have responded
        </div>
        <div v-if="canViewClickerSubmissions" class="d-flex align-items-start">
          <div style="flex: 1; max-width: 300px; padding-right: 40px;">
            <div v-if="!defaultSubmissionResults.length && piechartdata">
              <div
                v-for="(label, piechartdataIndex) in piechartdata.labels"
                :key="`pie-chart-data-${piechartdataIndex}`"
                style="display: flex; align-items: center; margin-bottom: 10px; font-size: large;"
              >
                <b-icon-square-fill
                  :style="`color:${piechartdata.datasets.backgroundColor[piechartdataIndex]}; margin-right: 8px;`"
                />
                <span style="margin-right: 4px;" v-html="label"/>
                <span
                  v-show="clickerAnswerShown && piechartdataIndex === correctAnswerIndex"
                >
                  <img
                    alt="Checkmark for correct answer"
                    :src="asset('assets/img/check-mark.png')"
                    style="height: 20px; margin-left: 4px;"
                  >
                </span>
              </div>
              <div style="flex: 2;">
                <pie-chart
                  :key="`pie-chart-${currentPage}-${chartUpdateKey}`"
                  :chartdata="piechartdata"
                  @pieChartLoaded="updateIsLoadingPieChart"
                />
              </div>
            </div>
          </div>
        </div>
        <div v-if="canViewClickerSubmissions && defaultSubmissionResults.length">
          <DefaultSubmissionResultsViewer
            :default-submission-results="defaultSubmissionResults"
            :clicker-answer-shown="Boolean(clickerAnswerShown)"
            :technology="questions[currentPage-1].technology"
            :is-phone="isPhone"
            :selected-clicker-view-option="selectedClickerViewOption"
          />
        </div>
      </div>
      <div v-show="!canViewClickerSubmissions || selectedClickerViewOption === 'question'">
        <div
          v-if="questions[currentPage-1] && questions[currentPage-1]['qti_json'] && getQtiJson()['qtiJson'] && showQtiJsonQuestionViewer"
        >
          <QtiJsonQuestionViewer
            :key="`qti-json-${currentPage}-${cacheIndex}-${questions[currentPage - 1].student_response}`"
            :qti-json="getQtiJson()['qtiJson']"
            :student-response="questions[currentPage - 1].student_response"
            :show-submit="false"
            :submit-button-active="getQtiJson()['submitButtonActive']"
            :show-reset-response="false"
            :presentation-mode="presentationMode"
            @submitResponse="receiveMessage"
            @resetResponse="resetSubmission"
          />
        </div>
        <div
          v-if="questions[currentPage-1] && questions[currentPage-1].technology_iframe.length"
        >
          <iframe
            :id="`clicker-technology-iframe-${currentPage}-${cacheIndex}`"
            :key="`clicker-technology-iframe-${currentPage}-${cacheIndex}`"
            v-resize="{ log: false }"
            aria-label="auto_graded_submission_text"
            width="100%"
            allowtransparency="true"
            :src="questions[currentPage-1].technology_iframe"
            frameborder="0"
            :title="getIframeTitle()"
            @load="cleanUpInstructorClickerView()"
          />
        </div>
      </div>
      <template #modal-footer>
        <div class="d-flex justify-content-between align-items-start flex-wrap">
          <!-- Left Side: Timer + Timer Buttons -->
          <div class="d-flex align-items-center flex-wrap">
            <span v-show="!canViewClickerSubmissions">
              <b-tooltip target="pause-timer" delay="750"
                         triggers="hover"
              >
                Pause the timer.
              </b-tooltip>
              <b-button v-show="!clickerPaused"
                        id="pause-timer"
                        variant="outline-info"
                        class="mr-2"
                        @click="pauseClicker"
              >
                <b-icon-pause-fill/>
                Pause
              </b-button>
              <b-tooltip target="resume-timer" delay="750"
                         triggers="hover"
              >
                Resume the timer.
              </b-tooltip>
              <b-button v-show="clickerPaused"
                        id="resume-timer"
                        variant="outline-info"
                        class="mr-2"
                        @click="resumeClicker"
              >
                <b-icon-play-fill/>
                Resume
              </b-button>
              <b-tooltip target="add-clicker-time" delay="750"
                         triggers="hover"
              >
                Add 15 seconds to the timer.
              </b-tooltip>
              <b-button id="add-clicker-time"
                        variant="outline-primary"
                        class="mr-2"
                        @click="addTimeToClicker"
              >
                +15 sec
              </b-button>
              <b-tooltip target="restart-timer-tooltip" delay="750"
                         triggers="hover"
              >
                Reset the timer to {{ clickerTimeForm.time_to_submit }}.
              </b-tooltip>
              <b-button id="restart-timer-tooltip"
                        variant="outline-primary"
                        class="mr-2"
                        @click="restartTimer()"
              >
                Restart Timer
              </b-button>
              <b-tooltip target="zero-timer-tooltip" delay="750"
                         triggers="hover"
              >
                Set the timer to zero, effectively closing the poll.
              </b-tooltip>
              <b-button id="zero-timer-tooltip"
                        variant="danger"
                        @click="closePoll(instructorClosedPollMessage)"
              >
                Zero Timer
              </b-button>
            </span>
          </div>
          <div class="d-flex align-items-center mt-2 mt-md-0">
            <b-tooltip target="clicker-results" delay="750"
                       triggers="hover"
            >
              Show the results of the poll. This will also close the poll.
            </b-tooltip>
            <b-button v-show="!canViewClickerSubmissions"
                      id="clicker-results"
                      variant="primary"
                      class="mr-2"
                      @click="viewClickerSubmissions(false)"
            >
              Results
            </b-button>
            <span
              v-show="(selectedClickerViewOption === 'submissions'
                || (selectedClickerViewOption === 'scores' && ('qti' === questions[currentPage-1].technology && !['multiple_choice','true_false'].includes(questions[currentPage-1].technology))))
                && canViewClickerSubmissions
                && clickerViewIsSubmissions
                && +responsePercent > 0
                && (['qti','webwork'].includes(questions[currentPage-1].technology)
                || ('h5p' === questions[currentPage-1].technology && ['True/False Question','Multiple Choice'].includes(questions[currentPage-1].h5p_type)))"
            >
              <b-tooltip target="show-hide-answer" delay="750"
                         triggers="hover"
              >
                Show/Hide the answer to the question.
              </b-tooltip>
              <b-button
                id="show-hide-answer"
                :variant="clickerAnswerShownText === 'Show' ? 'success' : ''"
                class="mr-2"
                @click="toggleShowClickerAnswer"
              >
                {{ clickerAnswerShownText }} Answer
              </b-button>
            </span>
            <span v-if="questions[currentPage-1].qti_answer_json && !isDiscussIt()">
              <QtiJsonAnswerViewer v-if="questions[currentPage-1].qti_answer_json"
                                   :key="`modal-answer-1-${questions[currentPage-1].id}`"
                                   :modal-id="questions[currentPage-1].id"
                                   :qti-json="questions[currentPage-1].qti_answer_json"
                                   :preview-or-solution="true"
              />
            </span>
            <b-tooltip target="open-clicker" delay="750"
                       triggers="hover"
            >
              Remove all student submissions and restart the question.
            </b-tooltip>
            <b-button v-show="!submissionsView"
                      id="open-clicker"
                      variant="outline-primary"
                      class="mr-2"
                      @click="openQuestion()"
            >
              Restart Question
            </b-button>
          </div>
        </div>
      </template>
    </b-modal>
    <b-modal v-if="questions[currentPage-1] && questions[currentPage-1].number_resets_available > 0"
             id="modal-reset-root-node-submission"
             title="Reset Submission"
    >
      <p>
        You currently can reset your submission {{ questions[currentPage - 1].number_resets_available }} time<span
        v-show="questions[currentPage-1].number_resets_available>1"
      >s</span>.
      </p>
      <p>By resetting the submission, you will be able to try the question again but your score will be reset to 0.</p>
      <template #modal-footer>
        <b-button
          size="sm"
          class="float-right"
          @click="$bvModal.hide('modal-reset-root-node-submission')"
        >
          Cancel
        </b-button>
        <b-button
          variant="primary"
          size="sm"
          class="float-right"
          @click="resetRootNodeSubmission()"
        >
          Reset Submission
        </b-button>
      </template>
    </b-modal>
    <b-modal v-if="learningTreeSrc.length > 0"
             id="modal-learning-tree"
             size="xl"
             hide-footer
             @shown="increaseLearningTreeModalSize"
             @hidden="reloadSingleQuestion"
    >
      <template #modal-header="{ close }">
        <!-- Emulate built in modal header close button action -->
        <h5>
          {{ questions[currentPage - 1].title }}
        </h5>
        <b-button size="sm" variant="outline-success" @click="$bvModal.hide('modal-learning-tree')">
          Exit Learning Tree
        </b-button>
      </template>
      <iframe
        allowtransparency="true"
        frameborder="0"
        :src="learningTreeSrc"
        style="width: 800px;min-width: 100%;height:800px"
        @load="increaseLearningTreeModalSize"
      />
    </b-modal>
    <b-modal id="modal-confirm-delete-open-ended-submissions"
             title="Confirm Delete Open Ended Submissions"
    >
      {{ confirmDeleteOpenEndedSubmissionsMessage }}
      <template #modal-footer>
        <b-button
          size="sm"
          class="float-right"
          @click="openEndedSubmissionType = originalOpenEndedSubmissionType;$bvModal.hide('modal-confirm-delete-open-ended-submissions')"
        >
          Cancel
        </b-button>
        <b-button
          variant="primary"
          size="sm"
          class="float-right"
          @click="$bvModal.hide('modal-confirm-delete-open-ended-submissions');updateOpenEndedSubmissionType (questions[currentPage-1].id)"
        >
          Do it!
        </b-button>
      </template>
    </b-modal>
    <b-modal v-if="questions[currentPage - 1] && questions[currentPage - 1].has_h5p_activity_set_submissions"
             id="modal-h5p-activity-set-submissions"
             title="Partial Submissions"
             hide-footer
             size="lg"
    >
      <b-table
        aria-label="Submissions"
        striped
        hover
        :no-border-collapse="true"
        :items="questions[currentPage - 1].h5p_activity_set_submissions"
        :fields="h5pVideoInteractionSubmissionsFields"
      >
        <template #cell(question)="data">
          <span v-html="data.value"/>
        </template>
        <template #cell(response)="data">
          <span v-html="data.value"/>
        </template>
      </b-table>
    </b-modal>
    <b-modal
      v-if="questionToEdit"
      :id="`modal-edit-question-${questionToEdit.id}`"
      :key="`modal-edit-question-${questionToEdit.id}`"
      :no-close-on-backdrop="true"
      :no-close-on-esc="true"
      size="xl"
      hide-footer
      dialog-class="modal-90"
      @hidden="reloadAndRemoveQuestionEditorUpdatedAt"
    >
      <template #modal-header>
        <div>
          <h2 class="h5 modal-title">
            Edit Question "{{ questionToEdit.title }}"
          </h2>
          <div>
            <small>ADAPT ID: <span id="adapt-question-id">{{ questionToEdit.id }}</span></small>
            <span class="text-info">
              <a id="copy-adapt-id-tooltip"
                 href=""
                 aria-label="Copy ADAPT ID"
                 @click.prevent="doCopy('adapt-question-id')"
              >
                <font-awesome-icon :icon="copyIcon"/>
              </a>
            </span>
            <b-tooltip target="copy-adapt-id-tooltip" delay="250"
                       triggers="hover focus"
            >
              Copy ADAPT ID {{ questionToEdit.id }}
            </b-tooltip>
          </div>
        </div>
        <button type="button" aria-label="Close"
                class="close"
                @click="$bvModal.hide(`modal-edit-question-${questionToEdit.id}`);reloadAndRemoveQuestionEditorUpdatedAt"
        >
          ×
        </button>
      </template>
      <CreateQuestion :key="`question-to-edit-${questionToEdit.id}-${questionToEdit.question_revision_id}`"
                      :question-to-edit="questionToEdit"
                      :parent-get-my-questions="reloadSingleQuestion"
                      :modal-id="'my-questions-question-to-view-questions-editor'"
                      :question-exists-in-own-assignment="questionToEdit.question_exists_in_own_assignment"
                      :question-exists-in-another-instructors-assignment="questionToEdit.question_exists_in_another_instructors_assignment"
                      @setQuestionRevision="setQuestionRevision"
      />
    </b-modal>
    <b-modal v-model="showAssignmentStatisticsModal"
             size="xl"
             title="Question Level Statistics"
             hide-footer
    >
      <b-container>
        <b-row v-if="showAssignmentStatistics && loaded && user.role === 3 && !isAnonymousUser">
          <b-col>
            <b-card header="default" header-html="<span class=&quot;font-weight-bold&quot;>Summary</span>">
              <b-card-text>
                <ul>
                  <li>{{ scores.length }} student submissions</li>
                  <li v-if="scores.length">
                    Maximum score of {{ max }}
                  </li>
                  <li v-if="scores.length">
                    Minimum score of {{ min }}
                  </li>
                  <li v-if="scores.length">
                    Mean score of {{ mean }}
                  </li>
                  <li v-if="scores.length">
                    Standard deviation of {{ stdev }}
                  </li>
                </ul>
              </b-card-text>
            </b-card>
          </b-col>
          <b-col>
            <HistogramAndTableView :chartdata="chartdata"
                                   :height="300"
                                   :width="300"
            />
          </b-col>
        </b-row>
      </b-container>
    </b-modal>

    <b-modal id="modal-cannot-give-up-yet"
             title="Submit Before Giving Up"
             hide-footer
    >
      <p>
        You'll be able to "give up" after you attempt this problem at least once. And, if you're not
        sure how to start this problem, a good strategy is to always go back to your notes or textbook to
        either find a related problem or review the underlying concept.
      </p>
    </b-modal>

    <b-modal v-if="questions[currentPage - 1]"
             id="modal-hint"
             title="Hint"
    >
      <b-alert :show="user.role === 2" variant="info">
        Students receive a {{ hintPenaltyIfShownHint }}% penalty for viewing the hint.
      </b-alert>
      <div id="hint-html">
      <span v-html="questions[currentPage - 1].hint"/>
      </div>
      <template #modal-footer="{ ok}">
        <b-button
          size="sm"
          variant="primary"
          @click="$bvModal.hide('modal-hint')"
        >
          OK
        </b-button>
      </template>
    </b-modal>
    <b-modal id="modal-confirm-show-hint"
             title="Confirm Show Hint"
    >
      <p v-if="questions[currentPage - 1]">
        <span v-if="hintPenaltyIfShownHint === 0">
          You can view a hint and no penalty will be applied.
        </span>
        <span v-if="hintPenaltyIfShownHint !== 0">
          You can view a hint, but if you do, a penalty of {{ hintPenaltyIfShownHint }}% will be applied to your next submission.
        </span>
      </p>

      <template #modal-footer="{ ok, cancel }">
        <b-button size="sm" @click="$bvModal.hide('modal-confirm-show-hint')">
          Cancel
        </b-button>
        <b-button v-if="!questions[currentPage-1].shown_hint"
                  size="sm"
                  variant="primary"
                  @click="handleShownHint"
        >
          Confirm Showing Hint
        </b-button>
      </template>
    </b-modal>

    <b-modal id="modal-confirm-give-up"
             title="Confirm Giving Up"
    >
      <div v-if="questions[currentPage - 1]">
        <div v-show="questions[currentPage - 1].submission_score">
          <p>
            You can give up now and get access to the solution, but if you do, your current score of
            {{ questions[currentPage - 1].submission_score }} will be recorded.
          </p>
          <p>
            In addition, once the solution becomes available, you will no longer be able to submit a new
            response.
          </p>
        </div>
        <div v-show="!questions[currentPage - 1].submission_score">
          You can give up now and get access to the solution, but once the solution becomes available, you will no
          longer be able to submit a new
          response.
        </div>
      </div>
      <template #modal-footer="{ ok, cancel }">
        <b-button size="sm" @click="$bvModal.hide('modal-confirm-give-up')">
          Cancel
        </b-button>
        <b-button v-if="!questions[currentPage-1].show_solution"
                  size="sm"
                  variant="primary"
                  @click="handleShowSolution"
        >
          Confirm Giving Up
        </b-button>
      </template>
    </b-modal>
    <b-modal
      id="modal-reason-for-uploading-local-solution"
      title="Reason For Uploading Local Solution"
      size="lg"
    >
      <RequiredText/>
      <b-container>
        <b-form-group
          id="reason_for_uploading_local_solution"
          label-cols-sm="4"
          label-cols-lg="3"
          label="Reason For Upload*"
          label-for="reason_for_uploading_local_solution"
        >
          <b-form-row>
            <b-form-radio-group
              v-model="reasonForUploadingLocalSolution"
              stacked
            >
              <b-form-radio name="reason_for_uploading_local_solution" value="prefer_own_solution">
                I prefer to use my own solution
              </b-form-radio>

              <b-form-radio name="reason_for_uploading_local_solution" value="libretexts_solution_error">
                There's an error in the Libretexts solution
              </b-form-radio>
            </b-form-radio-group>
          </b-form-row>
        </b-form-group>
        <b-form-group
          v-show="reasonForUploadingLocalSolution === 'libretexts_solution_error'"
          label-cols-sm="4"
          label-cols-lg="3"
          label="Description of Error*"
          label-for="description_of_libretexts_solution_error"
        >
          <ckeditor
            id="description_of_libretexts_solution_error"
            v-model="libretextsSolutionErrorForm.text"
            tabindex="0"
            :config="richEditorSolutionErrorConfig"
            required
            :class="{ 'is-invalid': libretextsSolutionErrorForm.errors.has('text') }"
            @keydown="libretextsSolutionErrorForm.errors.clear('text')"
            @namespaceloaded="onCKEditorNamespaceLoaded"
            @ready="handleFixCKEditor()"
          />
          <has-error :form="libretextsSolutionErrorForm" field="text"/>
        </b-form-group>
      </b-container>
      <template #modal-footer="{ ok, cancel }">
        <b-button size="sm" @click="$bvModal.hide('modal-reason-for-uploading-local-solution')">
          Cancel
        </b-button>
        <b-button size="sm" variant="primary" @click="submitReasonForUploadingLocalSolution()">
          Submit
        </b-button>
      </template>
    </b-modal>

    <b-modal
      id="modal-update-completion-scoring-mode"
      ref="modalUpdateCompletionScoringMode"
      title="Update Completion Scoring Mode"

      size="lg"
    >
      <RequiredText/>
      <b-form-group
        label-cols-sm="5"
        label-cols-lg="4"
        label-for="completion_scoring_mode"
      >
        <template v-slot:label>
          Completion Scoring Mode*
        </template>

        <b-form-radio-group id="completion_scoring_mode"
                            v-model="completionScoringModeForm.completion_scoring_mode"
                            stacked
                            required
                            :class="{ 'is-invalid': completionScoringModeForm.errors.has('completion_scoring_mode') }"
                            @keydown="completionScoringModeForm.errors.clear('completion_scoring_mode')"
        >
          <b-form-radio value="100% for either">
            100% of points for either auto-graded or open-ended submission
          </b-form-radio>
          <b-form-radio value="split">
            <input v-model="completionScoringModeForm.completion_split_auto_graded_percentage"
                   class="percent-input"
                   @keyup="completionSplitOpenEndedPercentage = updateCompletionSplitOpenEndedSubmissionPercentage(completionScoringModeForm)"
                   @click="completionScoringModeForm.completion_scoring_mode = 'split'"
                   @keydown="completionScoringModeForm.completion_scoring_mode = 'split'"
            >% of points awarded for an auto-graded
            submission<br>
            <span v-if="!isNaN(parseFloat(completionSplitOpenEndedPercentage))">
              <input v-model="completionSplitOpenEndedPercentage"
                     class="percent-input"
                     disabled
                     :aria-disabled="true"
              >%
              of the points awarded for an open-ended submission
            </span>
          </b-form-radio>
        </b-form-radio-group>
        <has-error :form="completionScoringModeForm" field="completion_scoring_mode"/>
      </b-form-group>
      <template #modal-footer="{ ok, cancel }">
        <b-button size="sm" @click="$bvModal.hide('modal-update-completion-scoring-mode')">
          Cancel
        </b-button>
        <b-button size="sm" variant="primary" @click="updateCompletionScoringMode(questions[currentPage-1].id)">
          Update
        </b-button>
      </template>
    </b-modal>

    <b-modal
      id="modal-attribution"
      ref="modalAttribution"
      hide-footer
      title="Attribution"
    >
      <div v-if="questions[currentPage-1]">
        <span v-html="questions[currentPage - 1].attribution !== null
          ? questions[currentPage - 1].attribution
          : autoAttributionHTML
        "
        />
      </div>
    </b-modal>
    <div v-if="modalEnrollInCourseIsShown" style="height: 375px"/>
    <b-modal
      id="modal-not-updated"
      ref="modalNotUpdated"
      hide-footer
      title="Not Updated"
    >
      <b-container>
        <b-row>
          <span class="font-weight-bold" style="font-size: large">
            {{ submissionDataMessage }}
          </span>
        </b-row>
      </b-container>
    </b-modal>
    <b-modal
      id="modal-thumbs-down"
      ref="modalThumbsUp"
      hide-footer
      size="lg"
      title="Submission Not Accepted"
    >
      <b-alert variant="danger" :show="true">
        <span class="font-weight-bold" style="font-size: large" v-html="submissionDataMessage"/>
      </b-alert>
    </b-modal>
    <b-modal
      id="modal-confirm-submission"
      ref="modalConfirmSubmission"
      hide-header-close
      no-close-on-backdrop
      no-close-on-esc
      title="Preview"
      @shown="renderMathJax()"
    >
      <div class="table-responsive">
        <table class="table table-striped pb-3" style="width:100%">
          <thead>
          <tr>
            <th scope="col">
              Submission
            </th>
          </tr>
          </thead>
          <tbody>
          <tr v-for="(item, itemIndex) in unconfirmedSubmission"
              :key="`unconfirmed-submission-${itemIndex}`"
          >
            <td>
              <div v-html="item ? item : 'Nothing submitted'"/>
            </td>
          </tr>
          </tbody>
        </table>
      </div>
      <template #modal-footer>
        <b-button
          size="sm"
          class="float-right"
          @click="cancelWebworkSubmission()"
        >
          Cancel
        </b-button>
        <b-button
          variant="primary"
          size="sm"
          class="float-right"
          @click="completeSubmission()"
        >
          Complete Submission
        </b-button>
      </template>
    </b-modal>
    <b-modal id="modal-assignment-completed"
             title="Assignment Completed"
             size="lg"
             hide-footer
    >
      You have submitted responses to all questions in this assignment.
    </b-modal>
    <b-modal
      id="modal-submission-accepted"
      ref="modalSubmissionAccepted"
      :title="modalSubmissionAcceptedTitle"
      :hide-footer="!learningTreeMessage"
      size="lg"
      :no-close-on-backdrop="true"
      @hidden="hideModalSubmissionAccepted"
    >
      <div v-if="learningTreeMessage || submissionDataMessage">
        {{ submissionDataMessage }}
      </div>
      <div v-if="questions[currentPage - 1] && questions[currentPage - 1].report">
        Be sure to paste the different sections of the report in the form below.
      </div>
      <div v-if="questions[currentPage-1]
        && (assessmentType === 'real time' || (assessmentType === 'delayed' && (user.role === 2 || solutionsReleased)))
        && !ish5pActivitySet"
      >
        <SubmissionArray :submission-array="submissionArray"
                         :question-id="questions[currentPage-1].id"
                         :user-id="user.id"
                         :assignment-id="+assignmentId"
                         :question-submission-array="questions[currentPage-1].submission_array"
                         :technology="questions[currentPage-1].technology"
                         :scoring-type="scoringType"
                         :user-role="user.role"
        />
        <div v-if="questions[currentPage - 1].student_response
          && questions[currentPage-1].qti_json
          && JSON.parse(questions[currentPage-1].qti_json).questionType === 'submit_molecule'
          && showSubmitMoleculeSubmission"
        >
          <SubmitMoleculeSubmission
            :key="questions[currentPage - 1].student_response"
            :question="questions[currentPage-1]"
            :scoring-type="scoringType"
          />
        </div>
        <div v-if="questions[currentPage - 1].qti_answer_json
          && JSON.parse(questions[currentPage-1].qti_json).questionType === 'marker'"
        >
          <MarkerSubmission
            :key="questions[currentPage - 1].student_response"
            :question="questions[currentPage-1]"
          />
        </div>
      </div>
      <template #modal-footer="{ cancel, ok }">
        <b-button size="sm" @click="$bvModal.hide('modal-submission-accepted')">
          Cancel
        </b-button>
        <b-button v-show="assessmentType === 'learning tree'"
                  size="sm"
                  variant="success"
                  @click="enterLearningTree"
        >
          View Learning Tree
        </b-button>
      </template>
    </b-modal>
    <div v-show="user.role === 3 && assessmentType === 'clicker'">
      <b-pagination
        v-if="user.role === 3 && solutionsReleased && !clickerApp"
        v-model="currentPage"
        :total-rows="questions.length"
        :per-page="perPage"
        limit="22"
        align="center"
        first-number
        last-number
        @change="changePage($event)"
      />
      <b-alert variant="info" :show="clickerMessage !== ''">
        <div v-html="clickerMessage"/>
      </b-alert>
      <b-form-row v-if="canViewClickerSubmissions" class="justify-content-center">
        <b-form-radio-group
          v-show="canViewClickerSubmissions"
          v-model="selectedClickerViewOption"
          name="clicker-view-options"
          plain
        >
          <label v-for="option in clickerViewOptions"
                 v-show="showClickerViewOption(canViewClickerSubmissions, questions[currentPage-1], option.value)"
                 :key="option.value"
                 :class="!isPhone() ? 'radio-label' : ''"
          >
            <input
              v-model="selectedClickerViewOption"
              type="radio"
              :value="option.value"
              :class="!isPhone() ? 'big-radio' : ''"
              @input="updateSelectedClickerViewOption()"
            >
            <span class="pr-1">{{ option.text }}</span>
          </label>
        </b-form-radio-group>
      </b-form-row>
    </div>
    <div v-show="selectedClickerViewOption === 'solution'">
      <div v-if="questions[currentPage-1]">
        <div v-show="questions[currentPage-1].solution_html"
             v-html="questions[currentPage - 1].solution_html !== null ? questions[currentPage-1].solution_html.replace(solutionHtmlHeader, '') : ''"
        />
        <div v-if="questions[currentPage - 1].qti_answer_json">
          <QtiJsonQuestionViewer :qti-json="questions[currentPage - 1].qti_answer_json"
                                 :show-qti-answer="true"
                                 :show-submit="false"
                                 :show-response-feedback="false"
                                 :preview-or-solution="true"
          />
        </div>
      </div>
    </div>
    <b-alert :show="showInvalidAssignmentMessage" variant="info">
      <div class="font-weight-bold">
        <p>
          It looks like you're trying to access an assignment with the URL {{ $router.currentRoute.path }}
          but we can't find that assignment. Please log out then log back in as your instructor may have updated the
          link.
          However, if you are still having issues and this is an embedded problem, please let your instructor know so
          that they can fix the URL.
        </p>
      </div>
    </b-alert>
    <EnrollInCourse :is-lms="isLMS"/>
    <Email id="contact-grader-modal"
           ref="email"
           extra-email-modal-text="Before you contact your grader, please be sure to look at the solutions first, if they are available."
           :from-user="user"
           title="Contact Grader"
           type="contact_grader"
           :subject="emailToGraderSubject"
    />
    <CannotAddAssessmentToBetaAssignmentModal/>
    <b-modal
      id="modal-cannot-update-solution"
      ref="modalCannotUpdateSolutionIfBetaAssignment"
      title="Cannot Update Solution"
      hide-footer
    >
      Since this a Beta assignment tethered to an Alpha assignment, you cannot update update the solution.
    </b-modal>
    <b-modal
      id="modal-cannot-update-points-if-beta-assignment"
      ref="modalCannotUpdatePointsIfBetaAssignment"
      title="Cannot Update"
      hide-footer
    >
      You are trying to update the points on a Beta assignment. Since this assignment is tethered to an Alpha
      assignment, you cannot update this value.
    </b-modal>
    <b-modal
      id="modal-should-not-edit-question-source-if-beta-assignment"
      ref="modalShouldNotEditQuestionSourceIfBetaAssignment"
      title="Should Not Edit"
      hide-footer
    >
      <p>
        You are trying to edit a question that is part of a Beta assignment. If you edit the question source, it will
        affect all other Beta assignments. Please get in touch with the Alpha instructor to see if an edit is possible.
      </p>
      <p>Please go to Tethered Courses in the Course Properties for their contact information.</p>
    </b-modal>
    <b-modal
      id="modal-properties"
      ref="modalProperties"
      title="Properties"
      size="xl"
      hide-footer
      @shown="createQrCode"
    >
      <b-container>
        <div class="pb-4">
          <b-card>
            <template #header>
              <h6 class="mb-0">
                Identifiers
              </h6>
            </template>
            <div class="mb-2">
              ADAPT ID: <span id="adaptID">{{ adaptId }}</span>
              <span class="text-info">
                <a
                  href=""
                  class="pr-1"
                  aria-label="Copy ADAPT ID"
                  @click.prevent="doCopy('adaptID')"
                >
                  <font-awesome-icon :icon="copyIcon"/>
                </a>
              </span>
            </div>
            <div v-if="!formativeQuestionURL" class="mb-2">
              ADAPT URL: <span id="currentURL">{{
                currentUrl
              }}</span>
              <span class="text-info">
                <a
                  href=""
                  class="pr-1"
                  aria-label="Copy ADAPT URL"
                  @click.prevent="doCopy('currentURL')"
                >
                  <font-awesome-icon :icon="copyIcon"/>
                </a>
              </span>
              <div class="mb-2 flex d-flex">
                QR Code
                <QuestionCircleTooltip id="summative-qr-code-tooltip" class="ml-1"/>
                <b-tooltip target="summative-qr-code-tooltip" delay="250"
                           triggers="hover focus"
                >
                  If logged in, students can summatively attempt this question by using this QR code. You can copy the
                  code by right-clicking it.
                </b-tooltip>
                <div id="qrCodeCanvas" ref="qrCodeCanvas" class="ml-2"/>
              </div>
            </div>
            <div v-if="formativeQuestionURL">
              <div class="mb-2">
                Formative URL
                <QuestionCircleTooltip id="formative-url-tooltip"/>
                <b-tooltip target="formative-url-tooltip" delay="250"
                           triggers="hover focus"
                >
                  Students can formatively attempt this question by visiting this URL.
                </b-tooltip>
                <span id="formative_question_url">{{ formativeQuestionURL }}</span> <a
                href=""
                class="pr-1"
                aria-label="Copy formative question URL"
                @click.prevent="doCopy('formative_question_url')"
              >
                <font-awesome-icon :icon="copyIcon"/>
              </a>
              </div>
              <div class="mb-2 flex d-flex">
                QR Code
                <QuestionCircleTooltip id="formative-qr-code-tooltip" class="ml-1"/>
                <b-tooltip target="formative-qr-code-tooltip" delay="250"
                           triggers="hover focus"
                >
                  Students can formatively attempt this question by using this QR code.
                </b-tooltip>
                <div id="qrCodeCanvas" ref="qrCodeCanvas" class="ml-2"/>
              </div>
            </div>
            <div v-if="!['text','qti'].includes(technology)" class="mb-2">
              Technology:
              <span id="technology" class="text-hide">
                {{ technology }}
              </span>
              {{ formattedTechnology }}
              <span class="text-info">
                <a
                  href=""
                  class="pr-1"
                  aria-label="Copy Technology"
                  @click.prevent="doCopy('technology')"
                >
                  <font-awesome-icon :icon="copyIcon"/>
                </a>
              </span>
            </div>
            <div v-if="a11yTechnologySrc" class="mb-2">
              A11y Technology URL: <span id="a11yTechnologySrc"
                                         v-html="a11yTechnologySrc"
            />
            </div>
            <div v-if="questions[currentPage - 1] && technology !== 'text'" class="mb-2">
              <span v-show="false" id="embed_formatively">
                {{ technology }}:{{ questions[currentPage - 1].technology_id }}
              </span>
              <span v-if="technology !== 'qti'">Embed Formatively: {{
                  technology
                }}:{{ questions[currentPage - 1].technology_id }} <a
                  href=""
                  class="pr-1"
                  aria-label="Copy Technology"
                  @click.prevent="doCopy('embed_formatively')"
                >
                <font-awesome-icon :icon="copyIcon"/>
              </a>
              </span>
            </div>
            <div v-if="technologySrc" class="mb-2">
              Technology URL: <span id="technologySrc"
                                    v-html="technologySrc"
            />
              <span id="technology_src" class="text-hide">
                {{ questions[currentPage - 1].technology_src }}
              </span>
              <span class="text-info">
                <a
                  href=""
                  class="pr-1"
                  aria-label="Copy Technology"
                  @click.prevent="doCopy('technology_src')"
                >
                  <font-awesome-icon :icon="copyIcon"/>
                </a>
              </span>
            </div>
          </b-card>
        </div>
        <div v-if="questions && questions[currentPage-1]" class="pb-4">
          <b-card>
            <template #header>
              <h6 class="mb-0">
                Embed Settings
              </h6>
            </template>
            <IframeInformation :assignment-information-shown-in-i-frame="assignmentInformationShownInIFrame"
                               :submission-information-shown-in-i-frame="submissionInformationShownInIFrame"
                               :attribution-information-shown-in-i-frame="attributionInformationShownInIFrame"
                               :parent-update-shown-in-i-frame="parentUpdateShownInIFrame"
            />
          </b-card>
        </div>
        <b-card>
          <template #header>
            <h6 class="mb-0">
              Description and Attribution
            </h6>
          </template>
          <b-form-group
            id="private_description"
            label-cols-sm="3"
            label-cols-lg="2"
          >
            <template v-slot:label>
              Private Description
              <QuestionCircleTooltip :id="'private-description-tooltip'"/>
              <b-tooltip target="private-description-tooltip" delay="250"
                         triggers="hover focus"
              >
                An optional description for the assessment. This description will only be viewable by you.
              </b-tooltip>
            </template>
            <b-form-textarea
              id="private_description"
              v-model="propertiesForm.private_description"
              rows="2"
              max-rows="2"
            />
          </b-form-group>
          <b-form-group
            id="attribution"
            label-cols-sm="3"
            label-cols-lg="2"
            label="Attribution"
            label-for="attribution"
          >
            <b-form-row>
              <toggle-button
                :width="90"
                class="mt-2"
                :value="autoAttribution"
                :sync="true"
                :font-size="14"
                :margin="4"
                :color="toggleColors"
                :labels="{checked: 'Auto', unchecked: 'Custom'}"
                @change="autoAttribution = !autoAttribution"
              />
            </b-form-row>
            <b-form-row v-show="autoAttribution">
              <span v-show="!autoAttributionHTML.length">No licensing information is available.</span>
              <span v-show="autoAttributionHTML.length" class="ml-2" v-html="autoAttributionHTML"/>
            </b-form-row>
            <ckeditor v-show="!autoAttribution"
                      v-model="propertiesForm.attribution"
                      :config="richEditorConfig"
                      tabindex="0"
                      @ready="handleFixCKEditor()"
            />
          </b-form-group>
          <b-button size="sm" @click="$bvModal.hide('modal-properties')">
            Cancel
          </b-button>
          <b-button size="sm" variant="primary" @click="updateProperties()">
            Update
          </b-button>
        </b-card>
      </b-container>
    </b-modal>
    <b-modal
      id="modal-reset-to-default-text"
      ref="modal"
      title="Confirm Reset To Default Text"
    >
      <p>
        By resetting to the default text, your current text submission will be removed.
      </p>
      <p><strong>Once the submission is removed, we will not be able to retrieve it!</strong></p>
      <template #modal-footer="{ ok, cancel }">
        <b-button size="sm" variant="primary" @click="$bvModal.hide('modal-reset-to-default-text')">
          Cancel
        </b-button>
        <b-button size="sm" variant="danger" @click="submitResetDefaultOpenEndedText">
          Delete Submission And Reset Default Text
        </b-button>
      </template>
    </b-modal>

    <b-modal
      id="modal-remove-solution"
      ref="modal"
      title="Confirm Remove Solution"
    >
      <p>
        Please confirm that you would like to remove this solution. Note that you will still be able to upload
        a different solution at any time.
      </p>
      <p v-if="questions.length && questions[currentPage-1].solution_html">
        Note that students will still have access to the Libretexts solution.
      </p>
      <template #modal-footer="{ cancel, ok }">
        <b-button size="sm" @click="$bvModal.hide('modal-remove-solution')">
          Cancel
        </b-button>
        <b-button size="sm" variant="primary" @click="submitRemoveSolution">
          Yes, remove this solution!
        </b-button>
      </template>
    </b-modal>
    <CannotDeleteAssessmentFromBetaAssignmentModal/>
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
      id="modal-upload-file"
      :title="getModalUploadFileTitle()"
      ok-title="Submit"
      size="lg"
      hide-footer
      @hidden="showAudioUploadComponent = true;handleCancel()"
    >
      <div v-if="!solutionTypeIsPdfImage">
        <audio-recorder
          ref="recorder"
          class="m-auto"
          :upload-url="audioSolutionUploadUrl"
          :time="1"
          :successful-upload="submittedAudioSolutionUpload"
          :failed-upload="failedAudioUpload"
        />
        <div v-if="showAddTextToSupportTheAudioFile">
          <hr>
          <b-form-group>
            Would you like to add text to support the audio?
            <span @click="showSolutionTextForm = true">
              <b-form-radio class="custom-control-inline" name="show-text-solution-form">Yes</b-form-radio>
            </span>
            <span @click="closeAudioSolutionModal"> <b-form-radio class="custom-control-inline"
                                                                  name="show-text-solution-form"
            >No</b-form-radio>
            </span>
          </b-form-group>
        </div>
        <div v-if="showSolutionTextForm">
          <div class="pt-3 pb-3">
            <ckeditor v-model="solutionTextForm.solution_text"
                      :config="richEditorConfig"
                      tabindex="0"
                      :class="{ 'is-invalid': solutionTextForm.errors.has('solution_text') }"
                      @keydown="solutionTextForm.errors.clear('solution_text')"
                      @ready="handleFixCKEditor()"
            />
            <has-error :form="solutionTextForm" field="solution_text"/>
          </div>
          <div>
            <span class="float-right"><b-button variant="primary" @click="submitSolutionText"
            >Save Text</b-button></span>
          </div>
        </div>
      </div>

      <div v-if="solutionTypeIsPdfImage">
        <b-form ref="form">
          <b-container v-show="uploadLevel === 'assignment' && (!showCurrentFullPDF && (cutups.length || fullPdfUrl))">
            <b-row align-h="center">
              <b-button class="ml-2" size="sm" variant="outline-primary" @click="showCurrentFullPDF = true">
                Use Current PDF
              </b-button>
            </b-row>
          </b-container>
          <b-container>
            <div v-if="!isOpenEndedAudioSubmission">
              <div v-if="bothFileUploadMode">
                <p>
                  Upload individual question submission. Optionally, upload an entire PDF with one submission per page
                  and let ADAPT cut up the PDF for you on the assignment's
                  <a :href="`/students/assignments/${assignmentId}/summary`" target="_blank">summary page</a>.
                </p>
              </div>
              <p v-if="compiledPDF">
                Please upload an entire PDF with one submission per page and let ADAPT cut up the PDF for you on the
                assignment's
                <a :href="`/students/assignments/${assignmentId}/summary`" target="_blank">summary page</a>.
              </p>
              <p>
                <strong>Important:</strong> For best results, don't crop any of your pages. In addition, please make
                sure that they are
                all
                oriented in the same direction.
              </p>
            </div>
            <file-upload
              v-if="isOpenEndedAudioSubmission && !compiledPDF"
              ref="upload"
              v-model="files"
              accept=".mp3"
              put-action="/put.method"
              @input-file="inputFile"
              @input-filter="inputFilter"
            />
            <b-button
              v-if="!isOpenEndedAudioSubmission && !compiledPDF"
              variant="primary"
              size="sm"
              @click="triggerFileSelect"
            >
              <span v-show="clickerApp">Take Photo or Upload New File</span>
              <span v-show="!clickerApp"> Upload New File</span>
            </b-button>
            <div id="open-ended-submission-file-upload">
              <file-upload
                v-if="!isOpenEndedAudioSubmission"
                ref="upload"
                v-model="files"
                accept=".pdf,.txt,.png,.jpg,.jpeg,.gif,.xlsx"
                put-action="/put.method"
                @input-file="inputFile"
                @input-filter="inputFilter"
              />
            </div>
          </b-container>
          <div v-show="uploadLevel === 'question' || !showCurrentFullPDF">
            <div class="example-drag">
              <div class="upload mt-3">
                <ul v-if="files.length && (preSignedURL !== '')">
                  <li v-for="file in files" :key="file.id">
                    <span :class="file.success ? 'text-success font-weight-bold' : ''">{{
                        file.name
                      }}</span> -
                    <span>{{ formatFileSize(file.size) }} </span>
                    <span v-if="file.size > 10000000">Note: large files may take up to a minute to process.</span>
                    <span v-if="file.error" class="text-danger">Error: {{ file.error }}</span>
                    <span v-else-if="file.active" class="ml-2">
                      <b-spinner small type="grow"/>
                      Uploading File...
                    </span>
                    <span v-if="processingFile">
                      <b-spinner small type="grow"/>
                      Processing file...
                    </span>
                    <b-button v-if="!processingFile && (preSignedURL !== '') && (!$refs.upload || !$refs.upload.active)"
                              variant="success"
                              size="sm"
                              style="vertical-align: top"
                              @click.prevent="$refs.upload.active = true"
                    >
                      Start Upload
                    </b-button>
                  </li>
                </ul>
              </div>

              <input type="hidden" class="form-control is-invalid">
              <div class="help-block invalid-feedback">
                {{ uploadFileForm.errors.get(uploadFileType) }}
              </div>
            </div>
          </div>
        </b-form>
      </div>
    </b-modal>
    <div v-if="inIFrame && showAssignmentInformation" class="text-center">
      <h5 v-if="(questions !==['init']) && canView">
        {{ name }}: Assessment {{ currentPage }} of {{ questions.length }}
      </h5>
    </div>
    <div class="vld-parent">
      <loading :active.sync="isLoading"
               :can-cancel="true"
               :is-full-page="true"
               :width="128"
               :height="128"
               color="#007BFF"
               background="#FFFFFF"
      />
      <div v-show="isInstructorLoggedInAsStudent">
        <LoggedInAsStudent :student-name="user.first_name + ' ' + user.last_name"/>
      </div>
      <div
        v-if="questions !==['init'] && !inIFrame && !cannotViewAssessmentMessage && !presentationMode && !clickerApp"
      >
        <PageTitle
          v-if="(assessmentType === 'flashcard' && user.role === 2) || (user.role !== 3 && assessmentType !== 'flashcard')"
          :title="getTitle(currentPage)"
          :adapt-id="getAdaptId()"
          :learning-tree-id="getLearningTreeId()"
          :show-formative-warning="questions[currentPage - 1] && questions[currentPage - 1].is_formative_question"
          :show-pencil="user && user.role===2"
          :assignment-id="+assignmentId"
          :algorithmic-question="questions[currentPage -1] && questions[currentPage - 1].algorithmic"
          :algorithmic-assignment="algorithmicAssignment"
          :question-id="questions.length && questions[currentPage-1].id"
          :is-instructor-with-anonymous-view="isInstructorWithAnonymousView"
          :clone-source-id="+(user && user.role===2 && questions[currentPage - 1] && questions[currentPage - 1].clone_source_id)"
          :open-ended-question-in-real-time-assignment="user && user.role === 2 && isOpenEnded && assessmentType === 'real time'"
          @updateCustomQuestionTitle="updateCustomQuestionTitle"
        />
        <div v-if="user.role === 3">
          <b-container fluid class="pt-2 pb-1 border-bottom">
            <b-row class="align-items-end">
              <b-col>
                <h1 class="page-title mb-0 text-primary font-weight-normal" style="font-size: 26px; line-height: 1.1;">
                  {{ getTitle(currentPage) }}
                </h1>
                <small v-show="questionStatus"
                       :class="getQuestionStatusClass()"
                       style="position: relative; top: -2px;"
                >Status: {{ capitalize(questionStatus) }}
                  <QuestionCircleTooltip v-if="user.fake_student" :id="'fake-student-status-open-tooltip'"/>
                  <b-tooltip target="fake-student-status-open-tooltip" delay="250"
                             triggers="hover focus"
                  >
                    Assignments are always open for "fake students"
                  </b-tooltip>
                </small>
              </b-col>
              <b-col cols="auto" class="text-right">
                <div
                  v-if="user.role === 3
                    && !isAnonymousUser
                    && questions[currentPage - 1]"
                  class="font-weight-bold"
                  style="font-size: 16px;"
                >
                  <span
                    v-if="(isFlashcard() && (questions[currentPage - 1].last_submitted === 'N/A')) || (!isFlashcard() && (!showScores || (questions[currentPage - 1].last_submitted === 'N/A' && isNaN(questions[currentPage - 1].submission_file_score))
                      || typeof(questions[currentPage - 1].total_score) === 'undefined'))"
                  >-/</span>
                  <span v-else>{{
                      questions[currentPage - 1].total_score * 1
                    }}/</span>{{ questions[currentPage - 1].points * 1 }}
                  point{{ 1 * (questions[currentPage - 1].points) !== 1 ? 's' : '' }}
                </div>

                <small v-if="assessmentType === 'delayed' || (studentNonClicker() && assessmentType === 'real time')"
                       :class="getQuestionStatusClass()"
                >
                  <span v-show="assessmentType !== 'delayed' && numberOfAllowedAttempts !== 'unlimited'
                    && scoringType === 'p'"
                  >{{ numberOfRemainingAttempts }}</span>
                  <span v-show="(numberOfAllowedAttempts === 'unlimited'
                    && !isFormative) || assessmentType === 'delayed'"
                  >&infin; attempts left</span>
                </small>
              </b-col>
            </b-row>
          </b-container>
        </div>
      </div>
      <div v-if="questions.length && !initializing && inIFrame && !showSubmissionInformation">
        <div v-show="parseInt(questions[currentPage - 1].submission_count) > 0" class="text-center">
          <b-alert variant="success" show>
            <span class="font-weight-bold">
              You have successfully submitted a response on  {{ questions[currentPage - 1].last_submitted }}.
              <span v-if="showScores">  You received a score of {{
                  questions[currentPage - 1].submission_score
                }}.</span></span>
          </b-alert>
        </div>
      </div>
      <div v-if="questions.length && !initializing && !isLoading">
        <UpdateRevision :key="`update-revision`"
                        :assignment-id="+assignmentId"
                        :current-question="questions[currentPage-1]"
                        :pending-question-revision="pendingQuestionRevision"
                        :latest-question-revision-id="questions[currentPage-1].question_revision_id_latest"
                        :assignment-name="name"
                        :question-number="currentPage"
                        @reloadSingleQuestion="reloadSingleQuestion"
        />
        <div v-if="inIFrame && (user.role === 2)">
          <b-button variant="primary" size="sm" class="mb-3" @click="viewInADAPT">
            View in ADAPT
          </b-button>
          <b-alert variant="info" :show="true">
            <strong>You are currently logged in as an instructor. No responses will be saved.</strong>
          </b-alert>
        </div>
      </div>
      <div v-if="user.role === 3 && cannotViewAssessmentMessage">
        <b-alert variant="info" show>
          <span v-if="!assignmentShown">
            This assessment is part of an assignment which is not yet being shown to any students in this course. If you would like,
            we can ask your instructor to <b-button size="sm" variant="primary"
                                                    @click="contactInstructorToReleaseAssignment"
          >publish the assignment</b-button>.</span>
          <span v-if="assignmentShown" class="font-weight-bold">
            This assignment will become available on {{
              $moment(availableOn, 'YYYY-MM-DD HH:mm:ss A').format('M/D/YY')
            }} at {{ $moment(availableOn, 'YYYY-MM-DD HH:mm:ss A').format('h:mm A') }}.
          </span>
        </b-alert>
      </div>
      <div v-if="user.role === 3 && launchThroughLMSMessage">
        <b-alert variant="info" show>
          <span class="font-weight-bold">
            This assessment is part of an assignment which should be initially launched through your LMS
            so that ADAPT can pass back your score.  Please log into your LMS and launch the assignment
            "{{ name }}".
          </span>
        </b-alert>
      </div>
      <div v-if="user.role === 2 && !inIFrame && !isLoading && !isInstructorWithAnonymousView">
        <AssessmentTypeWarnings :beta-assignments-exist="betaAssignmentsExist"/>
      </div>
      <div v-if="user.role === 2 && questions[currentPage-1] && questions[currentPage-1].h5p_non_adapt">
        <b-alert variant="info" show>
          This H5P question has type "{{ questions[currentPage - 1].h5p_non_adapt }}" which is not on the <a
          href="https://chem.libretexts.org/Courses/Remixer_University/Mastering_ADAPT%3A_A_User%27s_Guide/07%3A_Building_H5P_Assessments/H5P-ADAPT_Assessment_Status"
          target="blank"
        >list of Adapt ready H5P questions</a>.
          Please attempt this question in Student View to verify that it is working as expected.
        </b-alert>
      </div>
      <div v-if="questions.length && !cannotViewAssessmentMessage && !launchThroughLMSMessage">
        <div :class="assignmentInformationMarginBottom">
          <b-container>
            <div v-if="isInstructor() && assessmentType === 'clicker'" class="mb-2">
              <h5>
                Presentation Mode:
                <toggle-button
                  :width="60"
                  class="mt-2"
                  :value="presentationMode"
                  :sync="true"
                  size="lg"
                  :font-size="14"
                  :margin="4"
                  :color="toggleColors"
                  :labels="{checked: 'On', unchecked: 'Off'}"
                  @change="togglePresentationMode($event)"
                />
              </h5>
              <PageTitle v-show="presentationMode && loaded"
                         :title="`Question #${currentPage}`"
                         :show-pencil="false"
                         @updateCustomQuestionTitle="updateCustomQuestionTitle"
              />
            </div>

            <ul v-if="!clickerApp" style="list-style-type:none" class="p-0 mt-2">
              <li
                v-if="isInstructor() && !isInstructorWithAnonymousView && assessmentType !== 'clicker' && !inIFrame"
                class="mb-2"
              >
                <span class="font-weight-bold">Usage Settings:</span>
                <toggle-button
                  :width="83"
                  class="mt-2"
                  :value="questionView === 'basic'"
                  :sync="true"
                  :font-size="14"
                  :margin="4"
                  :color="{checked: '#6c757d', unchecked:'#17a2b8' }"
                  :labels="{checked: 'Hidden', unchecked: 'Shown'}"
                  @change="toggleQuestionAttributes()"
                />
              </li>
              <li v-if="assessmentType !== 'clicker'
                && user.role === 3
                && ((!isFormative && !inIFrame && timeLeft>0)
                || (inIFrame && showAssignmentInformation))"
              >
                <span v-if="showCountdown">
                  <countdown :time="timeLeft"
                             @end="endClickerAssessment"
                  >
                    <template v-slot="props">
                      <span v-html="getTimeLeftMessage(props, assessmentType)"/>
                    </template>
                  </countdown>
                  <b-button size="sm" variant="outline-info" @click="showCountdown = false">
                    Hide
                  </b-button>
                </span>
                <span v-if="!showCountdown" class="pt-1">
                  <b-button size="sm" variant="outline-info" @click="showCountdown = true">
                    Show Time Until Due
                  </b-button>
                </span>
              </li>
            </ul>
            <div
              v-if="studentShowPointsNonClicker()
                && assessmentType === 'real time'
                && numberOfAllowedAttempts !== '1'
                && numberOfAllowedAttemptsPenalty
                && !isFormative
                && false"
            >
              Next Attempt Points: {{ maximumNumberOfPointsPossible }}
              <QuestionCircleTooltip :id="'real-time-per-attempt-penalty-tooltip'"/>
              <b-tooltip target="real-time-per-attempt-penalty-tooltip" delay="250"
                         triggers="hover focus"
              >
                A per attempt penalty of {{ numberOfAllowedAttemptsPenalty }}% is applied after the first
                attempt. {{ getHintPenaltyMessage() }} Applying any penalty, the maximum number of points possible for
                the next attempt is
                {{ maximumNumberOfPointsPossible }} points.
              </b-tooltip>
            </div>
            <div
              v-if="studentNonClicker() || (user.role === 3 && assessmentType === 'clicker' && solutionsReleased)"
            >
              <div v-if="isLocalMe && questions[currentPage-1].qti_json && false">
                Qti Json: {{ questions[currentPage - 1].qti_json }}<br><br>
                Qti Answer Json: {{ questions[currentPage - 1].qti_answer_json }}<br><br>
                Solution html: {{ questions[currentPage - 1].solution_html }}<br><br>
                Solution: {{ questions[currentPage - 1].solution }}<br><br>
              </div>
              <ul v-if="caseStudyNotesByQuestion.length && !user.formative_student" style="list-style:none"
                  class="pl-0 pb-0"
              >
                <li>
                  <span class="font-weight-bold">Submitted At:
                    <span
                      :class="{ 'text-danger': questions[currentPage - 1].last_submitted === 'N/A' }"
                    >{{
                        questions[currentPage - 1].last_submitted
                      }} </span>
                  </span>
                </li>
                <li v-if="showScores">
                  <span class="font-weight-bold">Score: {{
                      questions[currentPage - 1].submission_score
                    }}</span>
                </li>
              </ul>
            </div>

            <div v-if="studentNonClicker() && completionScoringModeMessage">
              <span class="font-weight-bold" v-html="completionScoringModeMessage"/>
            </div>
            <div v-show="false" id="action-icons" class="pb-1">
              <a id="question-properties-tooltip" href="" @click.prevent="openModalProperties()">
                <b-icon icon="gear"
                        aria-label="Properties"
                        class="text-muted"
                        scale="1.2"
                />
              </a>
              <b-tooltip target="question-properties-tooltip"
                         delay="750"
                         triggers="hover"
              >
                View the question's properties
              </b-tooltip>
              <CloneQuestion
                :learning-tree-id="questions[currentPage - 1].learning_tree_id ? questions[currentPage - 1].learning_tree_id : 0"
                :assignment-id="+assignmentId"
                class="pl-1"
                :question-id="questions[currentPage - 1].id"
                :is-forge-draft="!!questions[currentPage - 1].forge_source_id"
                :question-editor-user-id="questions[currentPage - 1].question_editor_user_id"
                :title="questions[currentPage - 1].title"
                :license="questions[currentPage - 1].license"
                :public="questions[currentPage - 1].public"
                :library="questions[currentPage - 1].library"
                :non-technology="+questions[currentPage - 1].non_technology"
                @reloadQuestions="getSelectedQuestions(assignmentId, questions[currentPage - 1].id)"
              />
              <RefreshQuestion v-if="false"
                               :assignment-id="parseInt(assignmentId)"
                               :question-id="questions[currentPage - 1].id"
                               :reload-question-parent="reloadQuestionParent"
                               :icon="true"
              />
              <a v-if="assessmentType !== 'learning tree'"
                 id="edit-question-tooltip"
                 href=""
                 @click.prevent="editQuestionSource(questions[currentPage-1])"
              >
                <b-icon icon="pencil"
                        aria-label="Edit Question"
                        class="text-muted"
                        scale="1.1"
                />
              </a>
              <b-tooltip target="edit-question-tooltip"
                         delay="750"
                         triggers="hover"
              >
                <span v-if="questions[currentPage-1].forge_source_id">
                  Edit the main Forge question ({{ questions[currentPage - 1].forge_source_id }}). To edit draft-specific settings (title, due dates), use the Settings button.
                </span>
                <span v-else>
                  Edit the question
                </span>
              </b-tooltip>
              <a v-if="assessmentType === 'learning tree'"
                 id="edit-learning-tree-tooltip"
                 href=""
                 @click.prevent="editLearningTree(questions[currentPage-1].learning_tree_id)"
              >
                <b-icon icon="pencil"
                        aria-label="Edit Learning Tree"
                        class="text-muted"
                        scale="1.1"
                />
              </a>
              <b-tooltip target="edit-learning-tree-tooltip"
                         delay="750"
                         triggers="hover"
              >
                Edit the learning tree
              </b-tooltip>
              <a id="remove-question-tooltip"
                 href=""
                 @click.prevent="openRemoveQuestionModal(questions[currentPage-1])"
              >
                <b-icon icon="trash"
                        aria-label="Remove Question"
                        class="text-muted"
                        scale="1.1"
                />
              </a>
              <b-tooltip target="remove-question-tooltip"
                         delay="750"
                         triggers="hover"
              >
                <div v-if="['forge','forge_iteration'].includes(questions[currentPage-1].qti_json_type)">
                  <span v-if="questions[currentPage-1].forge_source_id">
                    Drafts can be removed in the Forge Settings
                  </span>
                  <span v-else>
                    {{ getRemoveForgeQuestionMessage(questions[currentPage - 1]) }}
                  </span>
                </div>
                <div v-else>
                  Remove question from the assignment
                </div>
              </b-tooltip>

              <span v-show="!myFavoriteQuestionIds.includes(questions[currentPage-1].id)">
                <a id="add-to-favorites-tooltip"
                   href=""
                   @click.prevent="$bvModal.show('modal-save-to-my-favorites')"
                >
                  <font-awesome-icon :icon="heartIcon"
                                     aria-label="Add To My Favorites"
                                     class="text-muted"
                                     scale="1.1"
                  />
                </a>
                <b-tooltip target="add-to-favorites-tooltip"
                           delay="750"
                           triggers="hover"
                >
                  Add question to My Favorites
                </b-tooltip>
              </span>
              <span v-show="myFavoriteQuestionIds.includes(questions[currentPage-1].id)">
                <a id="remove-from-favorites-tooltip"
                   href=""
                   class="p-1"
                   @click.prevent="removeMyFavoritesQuestion()"
                >
                  <font-awesome-icon :icon="heartIcon"
                                     aria-label="Remove From My Favorites"
                                     class="text-danger"
                                     scale="1.1"
                  />
                </a>
                <b-tooltip target="remove-from-favorites-tooltip"
                           delay="750"
                           triggers="hover"
                >
                  Remove question from My Favorites
                </b-tooltip>
              </span>
            </div>
            <div v-if="instructorInNonBasicView() && !clickerApp">
              <div
                v-if="isLocked()"
              >
                <b-alert variant="info" :show="true">
                  <p>
                    This problem is locked. Since students have already submitted responses, you cannot update the
                    points per question.
                  </p>
                  <p v-if="!showUpdatePointsPerQuestion">
                    In addition, since you are computing points by question weights, you will not be able to remove the
                    question
                    as it will affect already submitted questions.
                  </p>
                </b-alert>
              </div>
              <b-form-row v-show="!isFormative" style="margin-left:0">
                This question is worth <span v-show="!showUpdatePointsPerQuestion" class="pl-1 pr-1"
              > {{ questions[currentPage - 1].points }} </span>
                <b-form-input
                  v-if="showUpdatePointsPerQuestion"
                  id="points"
                  v-model="questionPointsForm.points"
                  size="sm"
                  type="text"
                  placeholder=""
                  style="width:50px"
                  :class="{ 'is-invalid': questionPointsForm.errors.has('points') }"
                  class="ml-2 mr-2"
                  @keydown="enteredPoints =true;questionPointsForm.errors.clear('points')"
                />
                <has-error v-if="showUpdatePointsPerQuestion" :form="questionPointsForm" field="points"/>
                point{{ 1 * (questions[currentPage - 1].points) !== 1 ? 's' : '' }}<span
                v-show="showUpdatePointsPerQuestion"
              >.</span>
                <span v-show="!showUpdatePointsPerQuestion" class="pl-1"> with a weight of</span>
                <b-form-input
                  v-if="!showUpdatePointsPerQuestion"
                  id="weight"
                  v-model="questionWeightForm.weight"
                  size="sm"
                  type="text"
                  placeholder=""
                  style="width:60px"
                  :class="{ 'is-invalid': questionWeightForm.errors.has('weight') }"
                  class="ml-2 mr-2"
                  @keydown="enteredPoints = true;questionWeightForm.errors.clear('weight')"
                />
                <b-col>
                  <div class="float-left">
                    <b-button :variant="enteredPoints && !hasAtLeastOneSubmission ? 'success' : 'primary'"
                              size="sm"
                              class="mb-2"
                              :disabled="hasAtLeastOneSubmission"
                              @click="showUpdatePointsPerQuestion ? updatePoints(questions[currentPage-1].id): updateWeight(questions[currentPage-1].id)"
                    >
                      Update <span v-show="showUpdatePointsPerQuestion">Points</span><span
                      v-show="!showUpdatePointsPerQuestion"
                    >Weight</span>
                    </b-button>
                  </div>
                </b-col>
              </b-form-row>
              <div v-if="assessmentType === 'clicker'">
                <b-form-group
                  id="submission_time"
                >
                  <div class="d-inline-flex">
                    <label class="mr-2 mt-1">Time to Submit:</label>
                    <b-form-input
                      id="time_to_submit"
                      v-model="clickerTimeForm.time_to_submit"
                      type="text"
                      style="width: 200px"
                      @keydown="clickerTimeForm.errors.clear('time_to_submit')"
                    />
                    <span class="ml-2"><b-button variant="primary" size="sm" @click="updateClickerTimeToSubmit()">Update Time to Submit
                    </b-button></span>
                  </div>
                  <ErrorMessage :message="clickerTimeForm.errors.get('time_to_submit')"/>
                </b-form-group>
                <b-row
                  v-if="questions[currentPage - 1] && questions[currentPage - 1].solution_html || questions[currentPage-1].qti_answer_json"
                >
                  <label for="release_solution_when_question_is_closed" class="pl-3 mr-2">
                    Release solution when question is closed:
                    <QuestionCircleTooltip id="release-solution-when-question-is-closed-tooltip"/>
                  </label>
                  <b-tooltip target="release-solution-when-question-is-closed-tooltip" delay="250"
                             triggers="hover focus"
                  >
                    Once a clicker question is closed, you can let your students see the solution for reviewing
                    purposes. <span v-show="solutionsReleased"><br><br>Since you have released the solutions to this assignment at the assignment level,
                      this feature at the question level has been disabled.</span>
                  </b-tooltip>
                  <b-form-radio-group
                    id="release_solution_when_question_is_closed"
                    v-model="questions[currentPage-1].release_solution_when_question_is_closed"
                    :disabled="solutionsReleased"
                    @change="updateShowSolutionForClosedQuestion"
                  >
                    <b-form-radio name="release_solution_when_question_is_closed" value="1">
                      Yes
                    </b-form-radio>
                    <b-form-radio name="release_solution_when_question_is_closed" value="0">
                      No
                    </b-form-radio>
                  </b-form-radio-group>
                </b-row>
              </div>
              <b-row
                v-if="questions
                  && questions[currentPage-1]
                  && !isOpenEnded
                  && !isFormative
                  && !isDiscussIt()
                  && assignmentCanSubmitWork
                  && ['real time', 'delayed'].includes(assessmentType)"
              >
                <label for="can_submit_work_override" class="pl-3 mr-2">Can submit work</label>
                <b-form-radio-group
                  id="can_submit_work_override"
                  v-model="questions[currentPage-1].can_submit_work"
                  stacked
                  @change="updateCanSubmitWorkOverride"
                >
                  <b-form-radio name="can_submit_work" value="1">
                    Yes
                    <CanSubmitWorkTooltip/>
                  </b-form-radio>
                  <b-form-radio name="can_submit_work" value="0">
                    No
                  </b-form-radio>
                </b-form-radio-group>
              </b-row>
              <b-row align-h="center">
                <span class="pr-1 font-weight-bold" v-html="completionScoringModeMessage"/>
                <a href="" @click.prevent="openUpdateCompletionScoringModeModal()">
                  <b-icon v-if="completionScoringModeMessage"
                          icon="pencil"
                          class="text-muted"
                          aria-label="Edit completion scoring mode"
                  />
                </a>
              </b-row>
            </div>
            <b-form-row
              v-if="!isForge() && instructorInNonBasicView() && (openEndedSubmissionTypeAllowed || questions[currentPage-1].technology === 'text') && !isDiscussIt()"
              style="margin-left:0"
            >
              <span class="pr-2">
                Open-Ended Submission Type:
              </span>
              <b-form-select v-model="openEndedSubmissionType"
                             :options="compiledPDF ? openEndedSubmissionCompiledPDFTypeOptions : openEndedSubmissionTypeOptions"
                             :style="['0',0,'no submission, manual grading'].includes(openEndedSubmissionType) ? 'width:250px' : 'width:100px'"
                             size="sm"
                             @change="initUpdateOpenEndedSubmissionType(questions[currentPage - 1].id)"
              />
            </b-form-row>
            <RubricPropertiesModal :key="`rubric-properties-modal-${+showRubricProperties}`"
                                   :show-rubric-properties="showRubricProperties"
                                   :is-edit="questions[currentPage-1].rubric !== null"
                                   :rubric-info="{'rubric': questions[currentPage-1].rubric}"
                                   :is-template="false"
                                   :question-points="1 * (questions[currentPage - 1].points)"
                                   :assignment-id="+assignmentId"
                                   :question-id="questions[currentPage-1].id"
                                   @setCustomRubric="setCustomRubric"
                                   @hideRubricProperties="showRubricProperties = false"
            />
            <b-row
              v-show="user.role === 2 && (+openEndedSubmissionType !== 0 || isDiscussIt() || isForge()) && instructorInNonBasicView()"
              class="pl-3 pb-2 pt-2"
            >
              <span v-if="assessmentType !== 'clicker'
                      && questions[currentPage - 1].question_rubric_exists"
                    class="mr-2 mt-1"
              >
                <toggle-button
                  tabindex="0"
                  :width="questions[currentPage - 1].use_existing_rubric ? 230 : 185"
                  :value="questions[currentPage - 1].use_existing_rubric"
                  :sync="true"
                  :font-size="14"
                  :margin="4"
                  :color="toggleColors"
                  :aria-label="questions[currentPage - 1].use_existing_rubric ? 'Use Existing Question Rubric' : 'Add Overriding Rubric'"
                  :labels="{checked: 'Use Existing Question Rubric', unchecked: 'Use Overriding Rubric'}"
                  @change="initUpdateUseExistingRubric($event)"
                />
              </span>
              <span
                v-show="user.role === 2
                  && assessmentType !== 'clicker'
                  && (!questions[currentPage - 1].use_existing_rubric || (questions[currentPage - 1].use_existing_rubric && !questions[currentPage - 1].rubric))"
              >
                <b-button size="sm"
                          variant="outline-info"
                          @click="showRubricProperties = true"
                >
                  {{ questions[currentPage - 1].rubric ? 'View' : 'Add' }} Overriding Rubric
                </b-button>
                <span class="pl-2">
                  <b-button v-show="questions[currentPage - 1].overriding_rubric"
                            size="sm"
                            variant="outline-danger"
                            @click="initConfirmDeleteOverridingRubric"
                  >
                    Delete Overriding Rubric
                  </b-button>
                </span>
              </span>
            </b-row>
            <div v-if="instructorInNonBasicView() && !clickerApp">
              <span v-if="!questions[currentPage-1].solution && false">
                <b-button
                  class="mt-2 mb-2 ml-1"
                  variant="dark"
                  size="sm"
                  @click="isBetaAssignment
                    ? $bvModal.show('modal-cannot-update-solution')
                    : initOpenUploadSolutionModal()"
                >
                  Upload Local Solution
                </b-button>
                <a id="local_solution_tooltip"
                   href="#"
                >
                  <b-icon class="text-muted"
                          icon="question-circle"
                          aria-label="Explanation of local solutions"
                  />
                </a>
                <b-tooltip target="local_solution_tooltip"
                           triggers="hover focus"
                           delay="250"
                >
                  Optionally, you can provide your own solution. If this question has a Libretext solution
                  associated with it, your local solution will be shown to your students.
                </b-tooltip>
              </span>
              <b-button
                v-if="questions[currentPage-1].solution"
                class="mt-1 mb-2 ml-1"
                variant="danger"
                size="sm"
                @click="isBetaAssignment
                  ? $bvModal.show('modal-cannot-update-solution')
                  : $bvModal.show('modal-remove-solution')"
              >
                Remove Local Solution
              </b-button>
            </div>
          </b-container>
        </div>

        <b-container v-if="!clickerApp">
          <div v-show="(!user.formative_student || (user.formative_student && !$route.params.questionId))"
               class="overflow-auto mt-2"
               :class="{ 'clicker-pagination': assessmentType === 'clicker' }"
          >
            <b-pagination
              v-if="assessmentType !== 'flashcard' && ((assessmentType === 'clicker' && user.role === 2) || (assessmentType !== 'clicker' && ((inIFrame && questionNumbersShownInIframe) || (!inIFrame && questionNumbersShownOutOfIframe && (assessmentType !== 'clicker' || isInstructor() || pastDue)))))"
              v-model="currentPage"
              :total-rows="questions.length"
              :per-page="perPage"
              limit="22"
              align="center"
              :size="presentationMode ? 'lg' : ''"
              first-number
              last-number
              @change="assessmentType === 'clicker' ? reloadByQuestionNumber($event) : changePage($event)"
            >
              <template #page="{ page, active, disabled }">
                <span
                  style="cursor: pointer;"
                  :style="getPageStyle(page, active,questions[page-1].clicker_status)"
                  @click="currentPage = page"
                >
                  {{ page }}
                </span>
              </template>
            </b-pagination>
          </div>
          <div v-if="user.role === 5" class="mt-2 mb-2">
            <b-button
              size="sm"
              variant="primary"
              @click.prevent="editQuestionSource(questions[currentPage-1])"
            >
              Edit Question
            </b-button>
            <SolutionFileHtml v-if="showSolutionFileHTML"
                              :key="`instructor-solution-file-html-${cacheKey}`"
                              :questions="questions"
                              :current-page="currentPage"
                              class="pr-2"
                              :assignment-name="name"
                              :show-na="false"
                              :use-view-solution-as-text="true"
            />
            <span
              v-if="questions[currentPage-1].qti_answer_json && !isDiscussIt() && !isFlashcard() && isQtiOrForgeWithQtiAnswerSolution(questions[currentPage-1])"
            >
              <QtiJsonAnswerViewer
                :modal-id="questions[currentPage-1].id"
                :qti-json="questions[currentPage-1].qti_answer_json"
                :preview-or-solution="true"
              />
              <b-button size="sm"
                        variant="outline-info"
                        @click="$bvModal.show(`qti-answer-${questions[currentPage-1].id}`)"
              >
                View Correct Answer
              </b-button>
            </span>
          </div>

          <ForgeSettings
            :key="`forget-settings-${forgeSettingsKey}`"
            :assignment-id="+assignmentId"
            :current-page="+currentPage"
            :question-id="questions[currentPage-1].forge_source_id || questions[currentPage-1].id"
            :current-draft-question-id="questions[currentPage-1].id"
            @submitRemoveQuestion="submitRemoveQuestion"
            @addDraft="addDraft"
          />
          <!-- Button to open the modal -->
          <div class="mb-2">
            <b-button v-show="user.role === 2 && isForge()"
                      size="sm"
                      variant="outline-info"
                      @click="$bvModal.show('modal-forge-settings')"
            >
              Forge Settings
            </b-button>
          </div>
          <div v-show="isDiscussIt()" class="mb-2">
            <b-button
              v-show="user.role === 2"
              size="sm"
              variant="outline-info"
              @click="$bvModal.show('modal-discuss-it-settings')"
            >
              Discuss-it Settings
            </b-button>
          </div>
          <div v-if="[2,5].includes(user.role)" class="mt-2">
            <div
              v-if="questions.length
                && questions[currentPage-1].question_revision_id !== questions[currentPage-1].question_revision_id_latest
                && !questions[currentPage-1].viewing_latest_revision
                && !isAnonymousUser"
            >
              <b-alert show variant="warning" class="text-center">
                You are viewing question revision {{ questions[currentPage - 1].question_revision_number }}. This
                question has a more up-to-date revision.
                <span class="ml-2">
                  <b-button v-if="!processingUpdatingQuestionView"
                            size="sm"
                            variant="info"
                            @click="viewLatestRevision"
                  >
                    View Latest Revision
                  </b-button>
                  <span v-if="processingUpdatingQuestionView">
                    <b-spinner small type="grow"/>
                    Updating Question...
                  </span>
                </span>
                <b-button
                  size="sm"
                  variant="primary"
                  @click.prevent="showLatestRevision()"
                >
                  Update to Latest Revision
                </b-button>
              </b-alert>
            </div>
            <div
              v-if="questions.length && questions[currentPage-1].question_revision_id_original"
            >
              <b-alert show variant="info" class="text-center">
                You are viewing the most up-to-date revision; your assignment uses an older
                revision.<span class="ml-2">
                  <b-button v-if="!processingUpdatingQuestionView" size="sm" variant="info"
                            @click="viewCurrentRevision();"
                  >
                    View Revision in Assignment
                  </b-button>
                  <span v-if="processingUpdatingQuestionView">
                    <b-spinner small type="grow"/>
                    Updating Question...
                  </span>
                </span>
                <b-button
                  size="sm"
                  variant="primary"
                  @click.prevent="showLatestRevision()"
                >
                  Update to Latest Revision
                </b-button>
              </b-alert>
            </div>
          </div>
        </b-container>
        <b-container
          v-if="questions[currentPage-1] && questions[currentPage-1].learning_tree_id"
        >
          <b-row class="pl-3 pb-2">
            <b-button
              v-show="user.role !== 3 || questions[currentPage-1].submission_count || questions[currentPage-1].at_least_one_learning_tree_node_submission"
              size="sm"
              variant="success"
              @click="enterLearningTree"
            >
              Enter Learning Tree
            </b-button>
            <span class="pl-2">

              <b-button v-show="questions[currentPage-1] && questions[currentPage-1].number_resets_available > 0"
                        size="sm"
                        variant="info"
                        @click="$bvModal.show('modal-reset-root-node-submission')"
              >
                Reset Submission
              </b-button>
            </span>
          </b-row>
          <ul v-show="user.role === 3" style="list-style:none;" class="pl-1">
            <li>
              <span class="font-weight-bold">
                Number of resets currently available:</span> {{ questions[currentPage - 1].number_resets_available }}
              <span>
                <QuestionCircleTooltip :id="'learning-tree-number-resets-available-tooltip'"/>
                <b-tooltip target="learning-tree-number-resets-available-tooltip" delay="250"
                           triggers="hover focus"
                >
                  Resets are earned by completing paths within the learning tree.  With available resets, you can reset the original
                  submission and try again without penalty.
                </b-tooltip>
              </span>
            </li>
            <li>
              <span class="font-weight-bold">
                <span
                  v-show="(questions[currentPage-1].submission_count || questions[currentPage-1].at_least_one_learning_tree_node_submission) && canEarnLearningTreeReset()"
                >
                  Earn a reset for after completing {{
                    questions[currentPage - 1].number_of_successful_paths_for_a_reset
                  }} path<span
                  v-if="questions[currentPage - 1].number_of_successful_paths_for_a_reset>1"
                >s</span>.
                </span>
                <span v-show="!canEarnLearningTreeReset()">
                  Completing additional paths will not give you additional resets.
                </span>
              </span>
            </li>
          </ul>
        </b-container>
        <div v-if="isInstructorWithAnonymousView && questions.length && !isLoading" class="pb-3">
          <div>
            <b-form-group
              label-cols-sm="3"
              label-cols-lg="2"
              label="Filter By Question Type:"
              label-for="assessment_type"
            >
              <b-form-select id="assessment_type"
                             v-model="questionType"
                             style="width:280px"
                             class="mt-1"
                             :options="questionTypeOptions"
                             required
                             size="sm"
                             @change="filterByQuestionType($event)"
              />
              <span v-if="filteringByQuestionType" class="pl-2">
                <b-spinner small type="grow"/>
                Updating view...
              </span>
            </b-form-group>
          </div>
          <SolutionFileHtml v-if="showSolutionFileHTML"
                            :questions="questions"
                            :current-page="currentPage"
                            class="pr-2"
                            :assignment-name="name"
                            :show-na="false"
                            :use-view-solution-as-text="true"
          />
          <span
            v-if="questions[currentPage-1].qti_answer_json && !isDiscussIt() && !isFlashcard() && !isQtiOrForgeWithQtiAnswerSolution(questions[currentPage-1])"
          >
            <QtiJsonAnswerViewer v-if="questions[currentPage-1].qti_answer_json"
                                 :key="`modal-answer-${questions[currentPage-1].id}`"
                                 :modal-id="questions[currentPage-1].id"
                                 :qti-json="questions[currentPage-1].qti_answer_json"
                                 :preview-or-solution="true"
            />
            <b-button
              size="sm"
              variant="outline-info"
              @click="$bvModal.show(`qti-answer-${questions[currentPage-1].id}`)"
            >
              View Correct Answer
            </b-button>
          </span>
        </div>
        <b-container v-if="caseStudyNotesByQuestion.length">
          <b-row v-if="questions[currentPage - 1].common_question_text" class="p-3">
            <p>{{ questions[currentPage - 1].common_question_text }}</p>
          </b-row>
          <b-row>
            <Transition>
              <b-col v-if="showLeftColumn">
                <div class="d-flex d-inline-flex pl-2">
                  <CaseStudyNotesViewer :key="`case-study-notes-viewer-key-${caseStudyNotesViewerKey}`"
                                        :case-study-notes="caseStudyNotesByQuestion"
                  />
                  <div>
                    <b-button v-if="showRightColumn"
                              id="expand-case-study-notes-tooltip"
                              size="sm"
                              variant="outline-info"
                              @click="showHideLeftAndRightColumns(true, false)"
                    >
                      <font-awesome-icon
                        :icon="expandArrowsIcon"
                        title="Expand the view to just show the Case Study notes."
                      />
                      <b-tooltip target="expand-case-study-notes-tooltip" delay="750"
                                 triggers="hover"
                      >
                        Expand the view to just show the Case Study notes.
                      </b-tooltip>
                    </b-button>
                    <b-button v-if="!showRightColumn"
                              id="collapse-case-study-notes-tooltip"
                              size="sm"
                              variant="outline-info"
                              @click="showHideLeftAndRightColumns(true, true)"
                    >
                      <font-awesome-icon
                        :icon="exitExpandArrowsIcon"
                      />
                      <b-tooltip target="collapse-case-study-notes-tooltip" delay="750"
                                 triggers="hover"
                      >
                        Show both the Case Study notes and the question.
                      </b-tooltip>
                    </b-button>
                  </div>
                </div>
              </b-col>
            </Transition>
            <Transition>
              <b-col v-if="showRightColumn && showQtiJsonQuestionViewer">
                <div class="card p-2">
                  <div class="d-flex d-inline-flex">
                    <div v-if="questions[currentPage-1]['qti_json'] && getQtiJson()['qtiJson']">
                      <QtiJsonQuestionViewer
                        :key="`qti-json-${currentPage}-${cacheIndex}-${questions[currentPage - 1].student_response}`"
                        :qti-json="getQtiJson()['qtiJson']"
                        :student-response="questions[currentPage - 1].student_response"
                        :show-submit="[2,3,5].includes(user.role)"
                        :submit-button-active="getQtiJson()['submitButtonActive']"
                        :show-reset-response="Boolean(user.formative_student) & !isForge()"
                        :assignment-id="+assignmentId"
                        :question-id="+questions[currentPage-1].id"
                        @submitResponse="receiveMessage"
                        @resetResponse="resetSubmission"
                      />
                      <b-alert :show="!submitButtonActive" variant="info">
                        No additional submissions will be accepted.
                      </b-alert>
                    </div>
                    <div style="margin-left: auto">
                      <b-button v-if="showLeftColumn"
                                id="expand-question-tooltip"
                                size="sm"
                                variant="outline-info"
                                @click="showHideLeftAndRightColumns(false, true)"
                      >
                        <font-awesome-icon
                          :icon="expandArrowsIcon"
                          title="Expand the view to just show the Case Study notes"
                        />
                        <b-tooltip target="expand-question-tooltip" delay="750"
                                   triggers="hover"
                        >
                          Expand the view to just show the question.
                        </b-tooltip>
                      </b-button>
                      <b-button v-if="!showLeftColumn"
                                id="collapse-question-tooltip"
                                size="sm"
                                variant="outline-info"
                                @click="showHideLeftAndRightColumns(true, true)"
                      >
                        <font-awesome-icon
                          :icon="exitExpandArrowsIcon"
                        />
                        <b-tooltip target="collapse-question-tooltip" delay="750"
                                   triggers="hover"
                        >
                          Show both the Case Study notes and the question.
                        </b-tooltip>
                      </b-button>
                    </div>
                  </div>
                </div>
                <div class="pl-1 pt-3">
                  <b-button v-show="!clickerApp"
                            size="sm"
                            variant="outline-primary" @click="showAttributionModal"
                  >
                    Attribution
                  </b-button>
                </div>
              </b-col>
            </Transition>
          </b-row>
        </b-container>
        <b-container v-if="!caseStudyNotesByQuestion.length"
                     id="questionContainer"
                     ref="questionContainer"
        >
          <div v-if="false">
            {{ clickerStatus }}
          </div>
          <b-row v-show="assessmentType === 'clicker'
            && !modalInstructorClickerQuestionShown
            && user.role === 2
            && presentationMode" class="pb-8"
          >
            <b-col cols="12">
              <div
                class="text-center"
                style="margin-bottom:50px"
              >
                <b-button id="forward-arrow"
                          style="padding:10px;margin-right:10px"
                          variant="primary"
                          :disabled="currentPage === 1"
                          @click="movePageByArrow(currentPage-1)"
                >
                  <font-awesome-icon style="font-size:30px"
                                     :icon="arrowLeftIcon"
                  />
                </b-button>
                <b-button id="back-arrow"
                          style="padding:10px;width:50px"
                          variant="primary"
                          :disabled="currentPage === questions.length"
                          @click="movePageByArrow(currentPage+1)"
                >
                  <font-awesome-icon style="font-size:30px"
                                     :icon="arrowRightIcon"
                  />
                </b-button>
              </div>

              <b-card
                v-show="loaded && !modalInstructorClickerQuestionShown && user.role === 2 && presentationMode"
                style="border-width: 1px;border-color:black;padding:10px"
                class="text-center"
              >
                <b-button v-show="clickerStatus === 'show_open' && !openingClicker"
                          style="padding:20px"
                          variant="success"
                          @click="openQuestion"
                >
                  <span style="font-size:30px">Open</span>
                </b-button>
                <span v-show="openingClicker" style="font-size:30px"><b-spinner v-show="openingClicker"
                /> Loading... </span>

                <b-tooltip target="open-clicker-question-tooltip" delay="500"
                           triggers="hover focus"
                >
                  This question has already been opened. Opening the question will remove all submissions
                  and reset the timer.
                </b-tooltip>
                <b-button v-show="clickerStatus !== 'show_open'"
                          id="open-clicker-question-tooltip"
                          style="padding:20px"
                          variant="info"
                          @click="openQuestion"
                >
                  <span style="font-size:30px">Open</span>
                </b-button>
                <b-tooltip target="reset-clicker-question-tooltip" delay="500"
                           triggers="hover focus"
                >
                  Resetting this question will remove all student submissions and reset the timer without actually
                  opening the question.
                </b-tooltip>
                <b-button v-show="clickerStatus !== 'show_open'"
                          id="reset-clicker-question-tooltip"
                          style="padding:20px"
                          variant="danger"
                          @click="resetQuestion"
                >
                  <span style="font-size:30px">Reset</span>
                </b-button>
                <b-button v-show="responsePercent >0"
                          style="padding:20px"
                          variant="outline-info"
                          @click="viewSubmissions"
                >
                  <span style="font-size:30px">Submissions</span>
                </b-button>
              </b-card>
            </b-col>
          </b-row>
          <b-row>
            <Transition>
              <b-col v-if="showLeftColumn" v-show="!isLoading" :cols="questionCol">
                <div class="mb-2">
                  <span class="d-flex">
                    <ForgeDueDates
                      v-if="user.role === 3 && isForge()"
                      :key="`forge-due-dates-${questions[currentPage-1].id}`"
                      class="mr-2"
                      :question-title="getTitle(currentPage)"
                      :assignment-id="+assignmentId"
                      :parent-question-id="questions[currentPage-1].forge_source_id || questions[currentPage-1].id"
                      :current-draft-question-id="questions[currentPage-1].id"
                    />
                    <!-- Flashcard controls (Play/Pause / Shuffle / Settings) -->
                    <span v-if="assessmentType === 'flashcard'" class="pb-2">
                      <b-button
                        v-if="fcControls.autoplayEnabled"
                        size="sm"
                        :variant="fcControls.autoplayActive ? 'info' : 'outline-secondary'"
                        class="mr-1"
                        @click="$refs.qtiViewer.$refs.flashcardViewer.externalToggleAutoplay()"
                      >
                        <b-icon :icon="fcControls.autoplayActive ? 'pause-fill' : 'play-fill'" aria-hidden="true"/>
                        {{ fcControls.autoplayActive ? 'Pause' : 'Play' }}
                        <span v-if="fcControls.autoplayActive" class="ml-1">{{ fcControls.autoplayCountdown }}s</span>
                      </b-button>
                      <b-button
                        v-if="fcControls.showSettings"
                        size="sm"
                        variant="outline-secondary"
                        @click="$refs.qtiViewer.$refs.flashcardViewer.externalOpenSettings()"
                      >
                        <b-icon icon="gear-fill" aria-hidden="true"/>
                        Settings
                      </b-button>
                    </span>

                    <span v-if="!isDiscussIt()
                            && !isForge()
                            && showSubmissionInformation
                            && showQuestion
                            && assessmentType !== 'flashcard'
                            && !['learning tree','clicker'].includes(assessmentType)
                            && !isFormative"
                          class="pb-2"
                    >
                      <b-button
                        variant="primary"
                        size="sm"
                        @click="$bvModal.show('modal-submission-information')"
                      >
                        Submission Information
                      </b-button>
                      <span v-if="['real time','learning tree'].includes(assessmentType)
                        && canViewHintAtAssignmentLevel
                        && !questions[currentPage-1].answered_correctly_at_least_once
                        && questions[currentPage-1].hint_exists
                        && (user.role ===2 || canViewHint)"
                      >
                        <b-button
                          size="sm"
                          variant="outline-info"
                          @click="showHintModal()"
                        >
                          Show Hint
                        </b-button>
                      </span>
                      <span v-if="questionStatus !== 'closed'
                        && studentNonClicker()
                        && !isForge()
                        && !questions[currentPage-1].answered_correctly_at_least_once
                        && assessmentType === 'real time'
                        && numberOfAllowedAttempts !== '1'
                        && !questions[currentPage-1].solution_type
                        && (questions[currentPage-1].solution_exists || questions[currentPage-1].qti_json)"
                      >
                        <span id="i-give-up-tooltip">
                          <b-button
                            size="sm"
                            variant="outline-primary"
                            @click="questions[currentPage-1].submission_file_url
                              || questions[currentPage-1].submission_count || questions[currentPage-1].can_give_up
                              ? confirmGiveUp()
                              : $bvModal.show('modal-cannot-give-up-yet')"
                          >
                            I Give Up
                          </b-button>
                        </span>
                        <b-tooltip target="i-give-up-tooltip" delay="500"
                                   triggers="hover"
                        >
                          If you choose this option, you will be shown the solution to the question and will no longer to be able
                          to re-submit, regardless of whether you have resets available. In addition, you will be awarded the current number
                          of points that you currently have.
                        </b-tooltip>
                      </span>
                      <span v-if="false">Student and Instructor SolutionFileHtml</span>
                      <SolutionFileHtml v-if="showSolutionFileHTML"
                                        :key="`solution-file-html-key-${questions[currentPage-1].solution || questions[currentPage-1].solution_html}-${cacheKey}`"
                                        :questions="questions"
                                        :current-page="currentPage"
                                        class="m-0"
                                        :assignment-name="name"
                                        :use-view-solution-as-text="true"
                                        :show-na="false"
                                        :show-button="assessmentType !== 'clicker'"
                      />
                      <span
                        v-if="questions[currentPage-1].qti_answer_json && !isDiscussIt() && !isFlashcard() && isQtiOrForgeWithQtiAnswerSolution(questions[currentPage-1])"
                      >
                        <QtiJsonAnswerViewer v-if="questions[currentPage-1].qti_answer_json"
                                             :key="`modal-answer-${questions[currentPage-1].id}`"
                                             :modal-id="questions[currentPage-1].id"
                                             :qti-json="questions[currentPage-1].qti_answer_json"
                                             :preview-or-solution="true"
                        />
                        <b-button
                          v-show="assessmentType !== 'clicker'"
                          size="sm"
                          variant="outline-info"
                          @click="$bvModal.show(`qti-answer-${questions[currentPage-1].id}`)"
                        >
                          View Correct Answer
                        </b-button>
                      </span>
                      <span v-if="showScores && showAssignmentStatistics && !isInstructor() && scores.length"
                            class="pt-2"
                      >
                        <b-button variant="outline-primary" size="sm" @click="openShowAssignmentStatisticsModal()">
                          View Statistics
                        </b-button>
                      </span>
                    </span>
                  </span>
                  <div v-if="attachments.length && questions[currentPage-1].id" class="pb-1 pt-1">
                    <b-icon-paperclip/>
                    Attachment<span
                    v-if="attachments.length>1"
                  >s</span>: <span v-show="attachments.length === 1">
                      <a
                        :href="downloadAttachmentUrl(attachments[0],questions[currentPage-1].id)"
                        aria-label="Download attachment"
                      >
                        {{ attachments[0].original_filename }}
                      </a>
                    </span>
                    <span v-if="attachments.length > 1">
                      <ul>
                        <li v-for="(attachment,attachmentIndex) in attachments"
                            :key="`attachments-${attachmentIndex}-${questions[currentPage-1].id}`"
                        >
                          <a
                            :href="downloadAttachmentUrl(attachment,questions[currentPage-1].id)"
                            aria-label="Download attachment"
                          >
                            {{ attachment.original_filename }}
                          </a>
                        </li>
                      </ul>
                    </span>
                  </div>
                  <div v-if="questions[currentPage - 1].can_submit_work
                    && questions[currentPage - 1].last_submitted !== 'N/A'
                    && submittedWorkPolicy !== 'optional'"
                  >
                    <b-alert variant="danger" show>
                      In order to receive credit for this submission, you must also submit your work below.
                    </b-alert>
                  </div>
                  <b-card
                    v-show="assessmentType !== 'clicker' || (assessmentType === 'clicker' && (user.role === 2 && !presentationMode) ||(user.role === 3 && clickerStatus !== 'neither_view_nor_submit'))"
                    no-body
                    class="p-2"
                    :border-variant="user.role === 3 ? getQuestionStatusClass(true) : ''"
                    :style="clickerApp || assessmentType === 'clicker' ? 'border:0' : ''"
                  >
                    <div>
                      <div
                        v-if="assessmentType !== 'clicker' && !caseStudyNotesByQuestion.length && showQuestion
                          || (assessmentType === 'clicker' && showQuestion && !canViewClickerSubmissions || (canViewClickerSubmissions && selectedClickerViewOption === 'question'))"
                        id="question-to-view"
                      >
                        <div v-if="user.role === 3">
                          <div v-if="['paused', 'resumed','view_and_submit'].includes(clickerStatus)"
                               class="text-center"
                          >
                            <div :style="isPhone() ? 'font-size:large' : 'font-size:x-large'"
                                 style="font-weight: bold"
                            >
                              Attempts left: {{
                                numberOfAllowedAttempts === 'unlimited' ? 'unlimited' : parseInt(numberOfAllowedAttempts) - parseInt(questions[currentPage - 1].submission_count)
                              }}
                            </div>
                          </div>
                        </div>
                        <div v-if="questions[currentPage-1].a11y_question_html && user.role === 3"
                             class="m-2"
                             v-html="formatA11YQuestionHtml(questions[currentPage - 1].a11y_question_html)"
                        />
                        <div
                          v-if="(!questions[currentPage-1].a11y_question_html && user.role === 3) || [2,4,5].includes(user.role)"
                        >
                          <div v-if="questions[currentPage-1].non_technology">
                            <iframe
                              id="open_ended_question_text"
                              :key="`non-technology-iframe-${currentPage}-${cacheIndex}`"
                              v-resize="{ log: false }"
                              aria-label="open_ended_question_text"
                              style="height: 30px"
                              width="100%"
                              scrolling="no"
                              :src="questions[currentPage-1].non_technology_iframe_src"
                              frameborder="0"
                              :title="getIframeTitle()"
                              @load="fixLinks('open_ended_question_text')"
                            />
                          </div>
                          <div v-if="questions[currentPage-1].id === 287740">
                            <HandsontableChart/>
                          </div>
                          <div
                            v-if="questions[currentPage-1]['qti_json'] && getQtiJson()['qtiJson'] && showQtiJsonQuestionViewer"
                          >
                            <span v-show="false" id="discussion-it-qti-json-question-viewer"/>
                            <QtiJsonQuestionViewer
                              ref="qtiViewer"
                              :key="assessmentType === 'flashcard' ? 'flashcard' : `qti-json-${currentPage}-${cacheIndex}-${questions[currentPage - 1].student_response}`"
                              :qti-json="getQtiJson()['qtiJson']"
                              :student-response="questions[currentPage - 1].student_response"
                              :show-submit="[2,3,5].includes(user.role) && !isDiscussIt() && !isForge() && !isFlashcard() && (assessmentType !== 'clicker' || timeLeft>0)"
                              :submit-button-active="getQtiJson()['submitButtonActive']"
                              :show-reset-response="Boolean(user.formative_student) && !isForge()"
                              :all-flashcards="allFlashcards"
                              :flashcard-settings="flashcardSettings"
                              :assignment-id="+assignmentId"
                              :question-id="+questions[currentPage-1].id"
                              :initial-question-id="+questionId"
                              :assessment-type="assessmentType"
                              @submitResponse="receiveMessage"
                              @resetResponse="resetSubmission"
                              @openContactGraderModal="openContactGraderModal"
                              @flashcardControlsChanged="onFlashcardControlsChanged"
                              @cardChanged="cardChanged"
                            />
                            <b-alert :show="!submitButtonActive && assessmentType !== 'clicker'" variant="info">
                              No additional submissions will be accepted.
                            </b-alert>
                          </div>
                          <div
                            v-if="questions[currentPage-1].technology_iframe.length
                              && !(user.role === 3 && clickerStatus === 'neither_view_nor_submit')"
                            :class="(!submitButtonActive && inIFrame) ? 'mb-4' :''"
                          >
                            <div
                              v-if="[4,5].includes(user.role) ||(technologySrcDoc === '' && questions[currentPage-1].technology !== 'webwork')"
                            >
                              <span v-show="testingH5p">
                                <b-alert :show="true" type="alert">
                                  You are testing on H5P staging.
                                </b-alert>
                              </span>
                              <iframe
                                :key="`technology-iframe-${currentPage}-${cacheIndex}-${resizeCacheIndex}`"
                                v-resize="{ log: false }"
                                aria-label="auto_graded_submission_text"
                                width="100%"
                                allowtransparency="true"
                                :src="questions[currentPage-1].technology_iframe"
                                frameborder="0"
                                :title="getIframeTitle()"
                              />
                            </div>
                            <div v-else>
                              <iframe
                                :key="`technology-iframe-srcdoc-${currentPage}-${cacheIndex}`"
                                v-resize="{ log: false, checkOrigin: false }"
                                aria-label="auto_graded_submission_text"
                                width="100%"
                                :srcdoc="technologySrcDoc"
                                frameborder="0"
                                allowtransparency="true"
                                :title="getIframeTitle()"
                              />
                            </div>
                            <b-alert :show="!submitButtonActive && iframeDomLoaded" variant="info">
                              No additional submissions will be accepted.
                            </b-alert>
                          </div>
                        </div>
                      </div>
                    </div>
                    <div v-if="!isForge() && isOpenEndedFileSubmission && user.role === 3">
                      <div class="mt-2 ml-1">
                        <b-button variant="primary"
                                  size="sm"
                                  :disabled="!submitButtonActive"
                                  @click="openUploadFileModal(questions[currentPage-1].id)"
                        >
                          Upload New File
                        </b-button>
                      </div>
                      <b-alert :show="!submitButtonActive" variant="info" class="mt-3">
                        No additional submissions will be accepted.
                      </b-alert>
                    </div>
                    <div v-if="isOpenEndedTextSubmission && user.role === 3 && !isAnonymousUser">
                      <div class="mt-1">
                        <ckeditor
                          ref="textSubmissionEditor"
                          :key="`${questions[currentPage-1].id}-${ckeditorTextSubmissionKey}`"
                          v-model="textSubmissionForm.text_submission"
                          tabindex="0"
                          aria-label="Text submission box"
                          :config="richEditorConfig"
                          @ready="handleFixCKEditorWithPasteWarning"
                          @namespaceloaded="onCKEditorNamespaceLoaded"
                        />
                      </div>
                      <b-container class="mt-2 mb-3">
                        <b-row>
                          <b-button v-if="questions[currentPage-1].open_ended_default_text"
                                    v-b-modal.modal-reset-to-default-text
                                    variant="danger"
                                    size="sm"
                                    class="mr-2"
                          >
                            Reset Default Text
                          </b-button>
                          <b-button variant="primary"
                                    size="sm"
                                    :disabled="!submitButtonActive"
                                    @click="submitText"
                          >
                            Submit
                          </b-button>
                        </b-row>
                      </b-container>
                      <div v-if="lastAutoSavedAt" class="text-muted small mt-1">
                        Last saved at {{ lastAutoSavedAt }}
                      </div>
                    </div>
                    <div v-if="isOpenEndedAudioSubmission && user.role === 3 && !isAnonymousUser" class="mt-3 mb-3">
                      <div class="h7">
                        Instructions
                      </div>
                      <div v-if="isPhone()">
                        <p>
                          Use your phone to record and upload your audio submission directly to ADAPT.
                        </p>
                        <NativeAudioVideoRecorder
                          :key="`new-submission-${questions[currentPage-1].id}-${previouslyUploadedAudioFile}`"
                          :recording-type="'audio'"
                          :upload-type="'submission'"
                          :assignment-id="+assignmentId"
                          :question-id="questions[currentPage-1].id"
                          :previously-uploaded-file="previouslyUploadedAudioFile"
                          @submitAudio="submitAudio"
                        />
                        <div class="text-center">
                          <b-button v-show="previouslyUploadedAudioFile"
                                    variant="primary"
                                    size="sm"
                                    @click="previouslyUploadedAudioFile =''"
                          >
                            Re-record
                          </b-button>
                        </div>
                      </div>
                      <div v-else class="ml-5">
                        <p>
                          Use the built-in "ADAPT recorder" below to record and upload your audio submission directly
                          to
                          ADAPT. After you hit record, click on the recording (for example, Record 1), and then click
                          the
                          disk
                          icon to save it and submit it.
                          Otherwise, you may record your audio submission as an .mp3 file with another program
                          (outside
                          of
                          ADAPT),
                          save the .mp3 file to your computer, then <a href=""
                                                                       variant="sm"
                                                                       @click.prevent="openUploadFileModal(questions[currentPage - 1].id)"
                        >
                          upload the .mp3 file</a> from your computer into ADAPT.
                        </p>
                        <audio-recorder
                          v-show="showAudioUploadComponent"
                          ref="uploadRecorder"
                          :key="questions[currentPage-1].id"
                          tabindex="0"
                          class="m-auto"
                          :upload-url="audioUploadUrl"
                          :time="1"
                          :successful-upload="submittedAudioUpload"
                          :failed-upload="failedAudioUpload"
                        />
                      </div>
                    </div>
                  </b-card>
                  <div v-if="assessmentType === 'clicker'
                         && user.role=== 3"
                       class="mt-4"
                  >
                    <RedirectToClickerModal
                      :key="`redirect-to-clicker-modal-${clickerAssignmentId}-${clickerQuestionId}-${redirectToClickerModalKey}`"
                      :assignment-id="+clickerAssignmentId"
                      :question-id="clickerQuestionId"
                      :show-modal="!clickerApp && (+clickerAssignmentId !== +assignmentId || questions[currentPage-1].id !==clickerQuestionId)"
                      @resetClickerAssignmentIdClickerQuestionId="resetClickerAssignmentIdClickerQuestionId()"
                    />

                    <div v-show="canViewClickerSubmissions && clickerViewIsSubmissions">
                      <div v-if="canViewClickerSubmissions">
                        <span v-if="questions[currentPage-1].qti_answer_json">
                          <QtiJsonAnswerViewer
                            :key="`modal-answer-${questions[currentPage-1].id}`"
                            :modal-id="questions[currentPage-1].id"
                            :qti-json="questions[currentPage-1].qti_answer_json"
                            :preview-or-solution="true"
                          />
                        </span>
                        <div v-show="!['question','solution'].includes(selectedClickerViewOption)"
                             style="font-size: 20px; margin-bottom: 30px;white-space: nowrap;"
                             class="text-center"
                        >
                          {{ responsePercent }}% of students have responded
                        </div>
                        <div style="flex: 1; max-width: 300px; padding-right: 40px;">
                          <div v-if="!defaultSubmissionResults.length && piechartdata">
                            <div
                              v-for="(label, piechartdataIndex) in piechartdata.labels"
                              :key="`pie-chart-data-${piechartdataIndex}`"
                              style="display: flex; align-items: center; margin-bottom: 10px; font-size: large;"
                            >
                              <b-icon-square-fill
                                :style="`color:${piechartdata.datasets.backgroundColor[piechartdataIndex]}; margin-right: 8px;`"
                              />
                              <span style="margin-right: 4px;" v-html="label"/>
                              <span
                                v-show="clickerAnswerShown && piechartdataIndex === correctAnswerIndex"
                              >
                                <img
                                  alt="Checkmark for correct answer"
                                  :src="asset('assets/img/check-mark.png')"
                                  style="height: 20px; margin-left: 4px;"
                                >
                              </span>
                            </div>
                            <div style="flex: 2;">
                              <pie-chart
                                :key="`pie-chart-${currentPage}-${chartUpdateKey}`"
                                :chartdata="piechartdata"
                                @pieChartLoaded="updateIsLoadingPieChart"
                              />
                            </div>
                          </div>
                        </div>
                      </div>
                      <div v-if="canViewClickerSubmissions && defaultSubmissionResults.length">
                        <DefaultSubmissionResultsViewer
                          :key="`default-submission-results-viewer-${defaultSubmissionResultsKey}`"
                          :default-submission-results="defaultSubmissionResults"
                          :clicker-answer-shown="Boolean(clickerAnswerShown)"
                          :technology="questions[currentPage-1].technology"
                          :is-phone="isPhone"
                          :question-id="questions[currentPage-1].id"
                          :selected-clicker-view-option="selectedClickerViewOption"
                        />
                      </div>
                    </div>
                  </div>
                  <b-card v-if="!isLoading && questions[currentPage-1]
                    && user.role === 2
                    && questions[currentPage-1].question_type ==='report'" class="mt-2"
                  >
                    <Report
                      :key="`instructor-report-key-${reportCacheKey}`"
                      :assignment-id="Number(assignmentId)"
                      :question-id="Number(questions[currentPage-1].id)"
                      :user-id="user.id"
                      :rubric-categories="questions[currentPage-1].rubric_categories"
                      :points="+questions[currentPage-1].points"
                      :overall-comments="questions[currentPage - 1].text_feedback"
                    />
                  </b-card>
                  <div class="pt-2 pb-2">
                    <!-- todo: completely removed the button when on a page -->
                    <span v-if="!inIFrame && (((!inIFrame || showAttribution) && questions[currentPage-1].attribution !== null
                      || (questions[currentPage-1].auto_attribution && autoAttributionHTML))
                      && !(user.role === 3 && clickerStatus === 'neither_view_nor_submit')
                      && !clickerApp && !presentationMode)"
                    >
                      <b-button size="sm" variant="outline-primary" @click="showAttributionModal">
                        Attribution
                      </b-button>
                    </span>
                    <span v-if="user.fake_student === 1 || ([2,5].includes(user.role) && !presentationMode)">
                      <b-button id="reset-submission-tooltip" size="sm" @click="resetSubmission"
                      >Reset Submission</b-button>
                      <b-tooltip target="reset-submission-tooltip" delay="500"
                                 triggers="hover focus"
                      >
                        While in Student View, you can reset the submission which may aid in testing questions.
                      </b-tooltip>
                    </span>
                    <span class="float-right d-flex align-items-center">
                      <span
                        v-if="questions[currentPage-1].can_submit_work"
                        class="pr-1"
                      >
                        <SubmitWork :key="`submit-work-${submitWorkKey}-${questions[currentPage-1].id}`"
                                    :disabled="false"
                                    :submitted-work-format-options="submittedWorkFormatOptions"
                                    :submit-button-active="true"
                                    :user-id="questions[currentPage-1].user_id"
                                    :assignment-id="+assignmentId"
                                    :question-id="+questions[currentPage-1].id"
                                    :submitted-work="questions[currentPage-1].submitted_work"
                                    :submitted-work-at="questions[currentPage-1].submitted_work_at"
                                    :optional="submittedWorkPolicy === 'optional'"
                                    :time-left-to-submit="timeLeft>0"
                                    @updateSubmittedWork="updateSubmittedWork"
                        />
                      </span>
                      <b-button
                        v-show="showContactGrader() && showContactInstructorAutoGraded && assessmentType !== 'flashcard'"
                        size="sm"
                        variant="outline-primary"
                        @click="openContactGraderModal( 'auto-graded')"
                      >
                        Contact Grader
                      </b-button>
                    </span>
                  </div>
                  <div>
                    <div
                      v-if="openEndedSubmissionType === 'rich text' && user.role === 2 && !inIFrame && !isInstructorWithAnonymousView"
                    >
                      <div class="mt-3">
                        <b-card header-html="<span class=&quot;font-weight-bold&quot;>Default Text</span>">
                          <p>
                            You can add default text for your students to see in their own text editors when they
                            attempt this question.
                          </p>
                          <ckeditor
                            :key="questions[currentPage-1].id"
                            v-model="openEndedDefaultTextForm.open_ended_default_text"
                            tabindex="0"
                            :config="richEditorConfig"
                            :class="{ 'is-invalid': openEndedDefaultTextForm.errors.has('open_ended_default_text') }"
                            @keydown="openEndedDefaultTextForm.errors.clear('open_ended_default_text')"
                            @namespaceloaded="onCKEditorNamespaceLoaded"
                            @ready="handleFixCKEditor()"
                          />
                          <has-error :form="openEndedDefaultTextForm" field="open_ended_default_text"/>
                        </b-card>
                        <b-container class="mt-2">
                          <b-row align-h="end">
                            <b-button variant="primary" size="sm" @click="submitDefaultOpenEndedText">
                              Update Default Text
                            </b-button>
                          </b-row>
                        </b-container>
                      </div>
                    </div>
                  </div>
                </div>
              </b-col>
            </Transition>
            <b-col v-if="assessmentType === 'clicker' && piechartdata && user.role === 2">
              <div>
                <div class="vld-parent">
                  <loading
                    :active.sync="isLoadingPieChart"
                    :can-cancel="true"
                    :is-full-page="false"
                    :width="128"
                    :height="128"
                    color="#007BFF"
                    background="#FFFFFF"
                  />
                </div>
              </div>
            </b-col>
            <b-row
              v-if="!isDiscussIt() && assessmentType !== 'clicker'
                && showAssignmentStatistics && loaded && user.role === 2 && !inIFrame && !isInstructorWithAnonymousView && !clickerApp && !isLoading"
            >
              <!-- Left: Statistics Card -->
              <b-col :cols="chartdata && chartdata.scores && chartdata.scores.length ? 6 : 12">
                <b-card
                  header="default"
                  header-html="<span class='font-weight-bold'>Question Statistics</span>"
                  class="h-100"
                >
                  <b-card-text>
                    <ul class="pl-1">
                      <li>{{ numberOfSubmissions }} student submissions</li>
                      <li v-if="scores.length">
                        Maximum score of {{ max }}
                      </li>
                      <li v-if="scores.length">
                        Minimum score of {{ min }}
                      </li>
                      <li v-if="scores.length">
                        Mean score of {{ mean }}
                      </li>
                      <li v-if="scores.length">
                        Standard deviation of {{ stdev }}
                      </li>
                    </ul>
                  </b-card-text>
                </b-card>
              </b-col>

              <!-- Right: Histogram -->
              <b-col cols="6" class="d-flex align-items-stretch">
                <div class="w-100 h-100">
                  <HistogramAndTableView
                    :chartdata="chartdata"
                    :height="400"
                  />
                </div>
              </b-col>
            </b-row>

            <b-col
              v-if="extraWaitForRenderComplete
                && !isLoading
                && showRightColumn
                && (user.role === 3)
                && (assessmentType !== 'clicker')
                && assessmentType !== 'flashcard'
                && (showSubmissionInformation || openEndedSubmissionType === 'file')"
              :class="isPhone() ? 'mx-auto p-4' : ''"
            >
              <div v-show="showScores && questions[currentPage-1].submission_score_override">
                <b-alert show variant="info">
                  Override score provided by instructor: {{ questions[currentPage - 1].submission_score_override }}
                </b-alert>
              </div>
              <b-row>
                <b-card
                  v-if="assessmentType === 'learning tree' && studentShowPointsNonClicker()"
                  header="default"
                  header-html="<h2 class=&quot;h7&quot;>Root Assessment Submission</h2>"
                  :class="{ 'mt-3': zoomedOut}"
                >
                  <div style="font-size:large">
                    <div
                      v-if="numberOfAllowedAttempts !== 'unlimited' && scoringType === 'p'"
                    >
                      {{
                        numberOfRemainingAttempts
                      }}
                    </div>
                    <div v-if="studentShowPointsNonClicker() && assessmentType === 'learning tree'">
                      Current Points: {{ questions[currentPage - 1].submission_score }}
                    </div>
                    <div
                      v-if="numberOfAllowedAttempts !== '1'
                        && numberOfAllowedAttemptsPenalty"
                    >
                      Next Attempt Points: {{ maximumNumberOfPointsPossible }}
                      <span>
                        <QuestionCircleTooltip :id="'learning-tree-per-attempt-penalty-tooltip'"/>
                        <b-tooltip target="learning-tree-per-attempt-penalty-tooltip" delay="250"
                                   triggers="hover focus"
                        >
                          A per attempt penalty of {{ numberOfAllowedAttemptsPenalty }}% is applied after the first
                          attempt.
                          {{ getHintPenaltyMessage() }}  With the penalty, the maximum number of points possible for the next attempt is
                          {{ maximumNumberOfPointsPossible }} points.
                        </b-tooltip>
                      </span>
                    </div>
                  </div>
                  <hr>
                  <div style="font-size: smaller">
                    <div>
                      Last submission: <span
                      :class="{ 'text-danger': questions[currentPage - 1].last_submitted === 'N/A' }"
                    >{{
                        questions[currentPage - 1].student_response
                      }}</span>
                      <div>
                        Submitted At:
                        <span
                          :class="{ 'text-danger': questions[currentPage - 1].last_submitted === 'N/A' }"
                        >{{
                            questions[currentPage - 1].last_submitted
                          }} </span>
                      </div>
                    </div>
                  </div>
                </b-card>
              </b-row>
            </b-col>

            <div
              v-if="isInstructor() && !isInstructorWithAnonymousView && !presentationMode && questionView !== 'basic' && !inIFrame && !isLoading"
              class="mt-1 libretexts-font" style="width:100%"
            >
              <div v-if="questions[currentPage - 1].text_question"
                   class="mt-3 libretexts-border"
              >
                <div class="mt-3" v-html="questions[currentPage - 1].text_question"/>
              </div>
              <div v-show="questions[currentPage - 1].a11y_auto_graded_question_id" class="mt-3 libretexts-border">
                <h2 class="editable mb-0">
                  A11y Question
                </h2>
                <span v-if="questions[currentPage - 1].a11y_auto_graded_question_id" class="mb-2"
                      style="font-size:14px;color:black"
                >
                  ADAPT ID: <span id="a11yAutoGradedAdaptId">{{
                    questions[currentPage - 1].a11y_auto_graded_question_id
                  }}</span>
                  <span class="text-info">
                    <a
                      href=""
                      class="pr-1"
                      aria-label="Copy a11y auto-graded ADAPT ID"
                      @click.prevent="doCopy('a11yAutoGradedAdaptId')"
                    >
                      <font-awesome-icon :icon="copyIcon"/>
                    </a>
                  </span>
                </span>

                <iframe
                  v-if="questions[currentPage-1].a11y_technology_iframe"
                  :key="`a11y-technology-iframe-${currentPage}-${cacheIndex}`"
                  v-resize="{ log: false }"
                  aria-label="a11y_auto_graded_text"
                  width="100%"
                  :src="questions[currentPage-1].a11y_technology_iframe"
                  frameborder="0"
                  :title="getIframeTitle()"
                />
                <div
                  v-if="questions[currentPage-1]['a11y_qti_json'] && getA11yQtiJson()['qtiJson']"
                >
                  <QtiJsonQuestionViewer
                    :key="`qti-json-${currentPage}-${cacheIndex}-${questions[currentPage - 1].student_response}`"
                    :qti-json="getA11yQtiJson()['qtiJson']"
                    :student-response="questions[currentPage - 1].student_response"
                    :show-submit="false"
                    :submit-button-active="getA11yQtiJson()['submitButtonActive']"
                    :show-reset-response="Boolean(user.formative_student) && !isForge()"
                    :assignment-id="+assignmentId"
                    :question-id="+questions[currentPage-1].id"
                  />
                  <b-alert :show="!submitButtonActive" variant="info">
                    No additional submissions will be accepted.
                  </b-alert>
                </div>
              </div>
              <div v-if="questions[currentPage-1].answer_html"
                   class="mt-3 libretexts-border"
              >
                <div class="mt-3" v-html="questions[currentPage - 1].answer_html"/>
              </div>
              <div v-if="questions[currentPage-1].solution_html"
                   class="mt-3 libretexts-border"
              >
                <div class="mt-3" id="solution-html" v-html="questions[currentPage - 1].solution_html"/>
              </div>
              <div v-if="questions[currentPage-1].hint"
                   class="mt-3 libretexts-border"
              >
                <div class="mt-3" v-html="questions[currentPage - 1].hint"/>
              </div>
              <div v-if="questions[currentPage-1].notes"
                   class="mt-3 libretexts-border"
              >
                <div class="mt-3" v-html="questions[currentPage - 1].notes"/>
              </div>
            </div>
          </b-row>
        </b-container>
      </div>
    </div>
    <div v-if="!initializing && !questions.length" class="mt-4">
      <b-alert show variant="warning" class="mt-3">
        <span class="alert-link">
          <span v-show="source === 'a'">This assignment currently has no assessments.</span>
          <span v-show="source === 'x'">This is an external assignment.  Please contact your instructor for more information.</span>
        </span>
      </b-alert>
    </div>
    <div v-if="showQuestionDoesNotExistMessage">
      <b-alert :show="true" variant="warning" class="mt-3">
        We could not find any questions associated with this assignment linked to:
        <p class="text-center m-2">
          <strong>{{ getWindowLocation() }}</strong>
        </p>
        Please ask your instructor to update this link so that it matches a question in the assignment.
      </b-alert>
    </div>
    <Report
      v-if="!isLoading && questions[currentPage-1]
        && (questions[currentPage-1].submission_file_exists && user.role === 3)
        && questions[currentPage-1].question_type ==='report'"
      :assignment-id="Number(assignmentId)"
      :question-id="Number(questions[currentPage-1].id)"
      :user-id="user.id"
      :rubric-categories="questions[currentPage-1].rubric_categories"
      :points="+questions[currentPage-1].points"
      :overall-comments="questions[currentPage - 1].text_feedback"
    />
  </div>
</template>

<script>
import axios from 'axios'
import Form from 'vform'
import { mapGetters } from 'vuex'

import { ToggleButton } from 'vue-js-toggle-button'
import { getAcceptedFileTypes, submitUploadFile } from '~/helpers/UploadFiles'
import { h5pResizer } from '~/helpers/H5PResizer'

import Loading from 'vue-loading-overlay'
import 'vue-loading-overlay/dist/vue-loading.css'

import { downloadSolutionFile, downloadSubmissionFile, getFullPdfUrlAtPage } from '~/helpers/DownloadFiles'
import { doCopy } from '~/helpers/Copy'

import Email from '~/components/Email'
import EnrollInCourse from '~/components/EnrollInCourse'
import RefreshQuestion from '~/components/RefreshQuestion'
import { getScoresSummary } from '~/helpers/Scores'
import CKEditor from 'ckeditor4-vue'

import PieChart from '~/components/PieChart'
import SolutionFileHtml from '~/components/SolutionFileHtml'

import libraries from '~/helpers/Libraries'

import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faCopy, faHeart } from '@fortawesome/free-regular-svg-icons'
import {
  faTree,
  faCheck,
  faArrowLeft,
  faArrowRight,
  faInfinity,
  faCompressArrowsAlt,
  faExpandArrowsAlt
} from '@fortawesome/free-solid-svg-icons'
import RemoveQuestion from '~/components/RemoveQuestion'

import Vue from 'vue'

import LoggedInAsStudent from '~/components/LoggedInAsStudent'
import CannotDeleteAssessmentFromBetaAssignmentModal from '../components/CannotDeleteAssessmentFromBetaAssignmentModal'

import AssessmentTypeWarnings from '~/components/AssessmentTypeWarnings'
import CannotAddAssessmentToBetaAssignmentModal from '~/components/CannotAddAssessmentToBetaAssignmentModal'

import IframeInformation from '~/components/IframeInformation'

import { updateCompletionSplitOpenEndedSubmissionPercentage } from '~/helpers/CompletionScoringMode'
import AllFormErrors from '~/components/AllFormErrors'
import { fixCKEditor } from '~/helpers/accessibility/fixCKEditor'
import HistogramAndTableView from '~/components/HistogramAndTableView'
import { defaultLicenseVersionOptions, updateAutoAttribution } from '~/helpers/Licenses'
import {
  getTechnologySrc,
  editQuestionSource,
  getQuestionToEdit,
  getQuestionRevisionToEdit,
  responseFormatOptions
} from '~/helpers/Questions'

import { getCaseStudyNotesByQuestion } from '~/helpers/CaseStudyNotes'
import CloneQuestion from '~/components/CloneQuestion'

import { fixInvalid } from '~/helpers/accessibility/FixInvalid'
import { makeFileUploaderAccessible } from '~/helpers/accessibility/makeFileUploaderAccessible'
import SavedQuestionsFolders from '~/components/SavedQuestionsFolders'
import CreateQuestion from '~/components/questions/CreateQuestion'
import QtiJsonQuestionViewer from '~/components/QtiJsonQuestionViewer'
import QtiJsonAnswerViewer from '~/components/QtiJsonAnswerViewer'
import CaseStudyNotesViewer from '~/components/questions/nursing/CaseStudyNotesViewer'

import { v4 as uuidv4 } from 'uuid'
import $ from 'jquery'
import QRCodeStyling from 'qr-code-styling'
import { qrCodeConfig } from '../helpers/QrCode'
import Report from '../components/Report.vue'
import UpdateRevision from '../components/questions/UpdateRevision.vue'
import uniqueId from 'vue-select/src/utility/uniqueId'
import {
  processReceiveMessage,
  hideSubmitButtonsIfCannotSubmit,
  addGlow,
  getTechnologySrcDoc
} from '~/helpers/HandleTechnologyResponse'
import { initCentrifuge } from '../helpers/Centrifuge'
import SubmissionArray from '../components/SubmissionArray.vue'
import SubmitMoleculeSubmission from '../components/SubmitMoleculeSubmission.vue'
import CanSubmitWorkTooltip from '../components/CanSubmitWorkTooltip.vue'
import SubmitWork from '~/components/SubmitWork.vue'
import RubricPointsBreakdownModal from '../components/RubricPointsBreakdownModal.vue'
import RubricPropertiesModal from '../components/RubricPropertiesModal.vue'
import RedirectToClickerModal from '../components/RedirectToClickerModal.vue'
import MarkerSubmission from '../components/MarkerSubmission.vue'
import {
  initClickerAssignmentsForEnrolledAndOpenCourses,
  resetClickerAssignmentIdClickerQuestionId
} from '../helpers/clicker'
import ErrorMessage from '../components/ErrorMessage.vue'
import { h5pOnLoadCssUpdates } from '../helpers/CSSUpdates'
import HandsontableChart from '../components/HandsonTable.vue'
import DefaultSubmissionResultsViewer from '../components/viewers/DefaultSubmissionResultsViewer.vue'
import { formatFileSize, inputFile, inputFilter } from '../helpers/UploadFiles'
import { isPhone } from '../helpers/isPhone'
import NativeAudioVideoRecorder from '../components/NativeAudioVideoRecorder.vue'
import { isQtiOrForgeWithQtiAnswerSolution, openEndedSubmissionTypeOptions } from '../helpers/Questions'
import { handleFixCKEditorWithPasteWarning } from '../helpers/ckeditor'
import ForgeSettings from '../components/ForgeSettings.vue'
import ForgeDueDates from '../components/ForgeDueDates.vue'

Vue.prototype.$http = axios // needed for the audio player

const VueUploadComponent = require('vue-upload-component')
Vue.component('file-upload', VueUploadComponent)

export default {
  middleware: 'auth',
  layout: window.config.clickerApp ? 'blank' : 'default',
  components: {
    ForgeDueDates,
    ForgeSettings,
    NativeAudioVideoRecorder,
    DefaultSubmissionResultsViewer,
    HandsontableChart,
    ErrorMessage,
    MarkerSubmission,
    RedirectToClickerModal,
    RubricPropertiesModal,
    RubricPointsBreakdownModal,
    SubmitWork,
    CanSubmitWorkTooltip,
    SubmitMoleculeSubmission,
    SubmissionArray,
    UpdateRevision,
    Report,
    CaseStudyNotesViewer,
    QtiJsonAnswerViewer,
    QtiJsonQuestionViewer,
    CannotDeleteAssessmentFromBetaAssignmentModal,
    FontAwesomeIcon,
    EnrollInCourse,
    Email,
    Loading,
    ToggleButton,
    SolutionFileHtml,
    PieChart,
    RemoveQuestion,
    ckeditor: CKEditor.component,
    FileUpload: VueUploadComponent,
    LoggedInAsStudent,
    AssessmentTypeWarnings,
    IframeInformation,
    CannotAddAssessmentToBetaAssignmentModal,
    RefreshQuestion,
    HistogramAndTableView,
    AllFormErrors,
    SavedQuestionsFolders,
    CreateQuestion,
    CloneQuestion
  },
  data: () => ({
    lastAutoSavedAt: '',
    autoSaveInterval: null,
    forgeSettingsKey: 0,
    assignmentCanSubmitWork: false,
    numberOfSubmissions: 0,
    submittedWorkPolicy: '',
    submittedWorkFormatOptions: [],
    attachments: [],
    questionStatus: '',
    ckeditorTextSubmissionKey: 0,
    pastedContent: false,
    hasHTML: false,
    previouslyUploadedAudioFile: '',
    isStructureImageUploader: false,
    resizeCacheIndex: 0,
    submissionsView: false,
    extraWaitForRenderComplete: false,
    solutionHtmlHeader: '<h2 class="editable">Solution</h2>',
    defaultSubmissionResultsKey: 0,
    justLoadedPage: true,
    selectedClickerViewOption: 'question',
    clickerViewOptions: [],
    clickerAnswerShownText: 'Show',
    responseFormatOptions: responseFormatOptions,
    defaultSubmissionResults: [],
    chartUpdateKey: 0,
    clickerViewIsSubmissions: true,
    pollClosed: false,
    instructorClosedPollMessage: '',
    currentTimeLeft: 0,
    clickerPaused: false,
    countdownKey: 0,
    redirectToClickerModalKey: 0,
    clickerQuestionId: 0,
    clickerAssignmentId: 0,
    useExistingRubric: false,
    rubricPointsBreakDownExists: false,
    showRubricPointsBreakdown: false,
    showRubricProperties: false,
    emailToGraderSubject: '',
    testingH5p: false,
    canContactInstructorAutoGraded: false,
    algorithmicAssignment: false,
    submitWorkKey: 0,
    showSubmitMoleculeSubmission: false,
    showSolutionFileHTML: false,
    modalInstructorClickerQuestionShown: false,
    clickerAnswerShown: false,
    canViewClickerSubmissions: false,
    openingClicker: false,
    clickerApp: false,
    pendingQuestionRevision: {},
    updateRevisionKey: 0,
    canContactGrader: false,
    learningTreeMessage: '',
    processingUpdatingQuestionView: false,
    modalSubmissionAcceptedTitle: 'Submission Accepted',
    reportCacheKey: 0,
    completedAllAssignmentQuestions: false,
    submissionArray: [],
    unconfirmedSubmission: [],
    questionNumbersShownOutOfIframe: true,
    formativeQuestionURL: '',
    technologySrcDoc: '',
    confirmDeleteOpenEndedSubmissionsMessage: '',
    enteredPoints: false,
    showQtiJsonQuestionViewer: false,
    submitButtonsDisabled: false,
    iframeDomLoaded: false,
    event: {},
    submitButtonActive: true,
    showLeftColumn: true,
    showRightColumn: true,
    taskStartTime: 0,
    totalTimeInTaskInactive: 0,
    startTimeTaskInactive: 0,
    tabFocus: false,
    caseStudyNotesViewerKey: 0,
    caseStudyNotesByQuestion: [],
    reviewQuestionPollingSetInterval: null,
    ish5pActivitySet: false,
    qtiJson: '',
    maxScore: null,
    h5pVideoInteractionSubmissionsFields: [
      {
        key: 'question',
        isRowHeader: true
      },
      'response'],
    technology: '',
    formattedTechnology: '',
    hintPenaltyIfShownHint: 0,
    questionNumbersShownInIframe: false,
    hintPenalty: 0,
    flashcardSettings: {},
    fcControls: {
      autoplayEnabled: false,
      autoplayActive: false,
      autoplayCountdown: 0,
      shuffleOn: false,
      showShuffle: false,
      showSettings: false
    },
    canViewHintAtAssignmentLevel: false,
    canViewHint: false,
    isFormative: false,
    isBetaAssignment: false,
    rubricCategories: [],
    questionToEdit: {},
    a11yTechnologySrc: '',
    cacheKey: 1,
    showCountdown: true,
    showUpdatePointsPerQuestion: false,
    numberOfRemainingAttempts: '',
    numberOfAllowedAttempts: '',
    numberOfAllowedAttemptsPenalty: '',
    maximumNumberOfPointsPossible: '',
    myFavoriteQuestions: {},
    currentFavoritesFolder: null,
    myFavoritesFolder: null,
    filteringByQuestionType: false,
    questionType: 'any-question-type',
    questionTypeOptions: [
      { text: 'Auto-graded or Open-ended', value: 'any-question-type' },
      { text: 'Auto-graded, only', value: 'auto-graded-only' },
      { text: 'Open-ended, only', value: 'open-ended-only' }
    ],
    reasonForUploadingLocalSolution: 'prefer_own_solution',
    libretextsSolutionErrorForm: new Form({
      text: '',
      question_id: 0
    }),
    showAudioUploadComponent: false,
    reload: 0,
    showHistogramView: true,
    allFormErrors: [],
    completionSplitOpenEndedPercentage: '',
    completionScoringModeForm: new Form({
      completion_scoring_mode: null,
      completion_split_auto_graded_percentage: null
    }),
    scoringType: '',
    completionScoringModeMessage: '',
    bCardCols: 4,
    zoomedOut: false,
    toggleColors: window.config.toggleColors,
    savedQuestions: [],
    myFavoriteQuestionIds: [],
    isAnonymousUser: false,
    isInstructorWithAnonymousView: false,
    launchThroughLMSMessage: false,
    isLMS: false,
    availableOn: '',
    assignmentShown: true,
    hasAtLeastOneSubmission: false,
    assignmentInformationShownInIFrame: false,
    submissionInformationShownInIFrame: false,
    attributionInformationShownInIFrame: false,
    cacheIndex: 1,
    modalEnrollInCourseIsShown: false,
    betaAssignmentsExist: false,
    autoAttributionHTML: '',
    autoAttribution: true,
    isInstructorLoggedInAsStudent: false,
    checkIcon: faCheck,
    bothFileUploadMode: false,
    compiledPDF: false,
    fullPdfUrl: '',
    handledOK: false,
    fileUploadKey: 1,
    preSignedURL: '',
    files: [],
    s3Key: '',
    questionView: 'basic',
    copyIcon: faCopy,
    heartIcon: faHeart,
    expandArrowsIcon: faExpandArrowsAlt,
    exitExpandArrowsIcon: faCompressArrowsAlt,
    treeIcon: faTree,
    infinityIcon: faInfinity,
    arrowLeftIcon: faArrowLeft,
    arrowRightIcon: faArrowRight,
    technologySrc: '',
    pageId: '',
    adaptId: '',
    ckeditor: {},
    isLoading: true,
    activeId: 0,
    learningTreeSrc: '',
    assignmentInformationMarginBottom: 'mb-3',
    showSubmissionInformation: true,
    showAssignmentInformation: false,
    showAttribution: true,
    showInvalidAssignmentMessage: false,
    presentationMode: false,
    defaultClickerTimeToSubmit: null,
    libraryText: '',
    libraryOptions: libraries,
    pastDue: false,
    clickerStatus: '',
    countDownKey: 0,
    savedText: 1,
    showSolutionTextForm: false,
    showAddTextToSupportTheAudioFile: false,
    responsePercent: '',
    isLoadingPieChart: true,
    correctAnswerIndex: null,
    piechartdata: {},
    clickerMessage: '',
    clickerMessageType: '',
    cannotViewAssessmentMessage: false,
    uploadedAudioSolutionDataType: '',
    showUploadedAudioSolutionMessage: false,
    uploadedAudioSolutionDataMessage: '',
    solutionFileHtml: '',
    openEndedSubmissionDataType: '',
    showOpenEndedSubmissionMessage: false,
    openEndedSubmissionDataMessage: '',
    solutionTypeIsPdfImage: true,
    audioSolutionUploadUrl: '',
    audioUploadUrl: '',
    shownSections: '',
    iFrameAssignmentInformation: true,
    iFrameSubmissionInformation: true,
    iFrameAttribution: true,
    inIFrame: false,
    editorData: '<p>Content of the editor.</p>',
    richEditorConfig: {
      toolbar: [
        { name: 'clipboard', items: ['Cut', 'Copy', '-', 'Undo', 'Redo'] },
        {
          name: 'basicstyles',
          items: ['Bold', 'Italic', 'Underline', 'Subscript', 'Superscript', '-', 'CopyFormatting', 'RemoveFormat']
        },
        {
          name: 'paragraph',
          items: ['NumberedList', 'BulletedList', '-', 'Indent', 'Blockquote', '-', 'JustifyLeft', 'JustifyCenter', 'JustifyRight', 'JustifyBlock']
        },
        { name: 'links', items: ['Link', 'Unlink'] },
        { name: 'insert', items: ['Table', 'HorizontalRule', 'Smiley', 'SpecialChar'] },
        { name: 'colors', items: ['TextColor', 'BGColor'] }
      ],
      removeButtons: '',
      resize_enabled: false,
      height: 200
    },
    richEditorSolutionErrorConfig: {
      toolbar: [
        { name: 'clipboard', items: ['Cut', 'Copy', '-', 'Undo', 'Redo'] },
        {
          name: 'basicstyles',
          items: ['Bold', 'Italic', 'Underline', 'Subscript', 'Superscript', '-', 'CopyFormatting', 'RemoveFormat']
        },
        { name: 'links', items: ['Link', 'Unlink'] },
        { name: 'insert', items: ['Table', 'HorizontalRule', 'Smiley', 'SpecialChar'] },
        { name: 'colors', items: ['TextColor', 'BGColor'] }
      ],
      removeButtons: '',
      height: 200,
      resize_enabled: false
    },
    isOpenEnded: false,
    isOpenEndedFileSubmission: false,
    isOpenEndedTextSubmission: false,
    isOpenEndedAudioSubmission: false,

    responseText: '',
    openEndedSubmissionTypeOptions: openEndedSubmissionTypeOptions,
    openEndedSubmissionCompiledPDFTypeOptions: [
      { value: 'file', text: 'PDF' },
      { value: 0, text: 'None' }
    ],
    showDidNotAnswerCorrectlyMessage: false,
    embedCode: '',
    canView: false,
    latePolicy: '',
    capitalFormattedAssessmentType: '',
    assessmentType: '',
    showPointsPerQuestion: false,
    showQuestionDoesNotExistMessage: false,
    maintainAspectRatio: false,
    showAssignmentStatisticsModal: false,
    showAssignmentStatistics: false,
    questionCol: 12,
    loaded: false,
    chartdata: null,
    assignmentInfo: {},
    scores: [],
    mean: 0,
    stdev: 0,
    max: 0,
    min: 0,
    range: 0,
    graderEmailSubject: '',
    to_user_id: false,
    showCurrentFullPDF: false,
    settingAsSolution: false,
    cutups: [],
    uploadLevel: 'assignment',
    timeLeft: 0,
    totalPoints: 0,
    uploadFileType: '',
    source: 'a',
    scoring_type: '',
    solutionsReleased: false,
    showScores: false,
    has_submissions_or_file_submissions: false,
    students_can_view_assignment_statistics: false,
    submissionDataType: 'danger',
    submissionDataMessage: '',
    showSubmissionMessage: false,
    processingFile: false,
    propertiesForm: new Form({
      private_description: '',
      auto_attribution: '',
      attribution: ''
    }),
    licenseVersionOptions: [],
    defaultLicenseVersionOptions: defaultLicenseVersionOptions,
    questionSubmissionPageForm: new Form({
      page: ''
    }),
    clickerTimeForm: new Form({
      time_to_submit: ''
    }),
    solutionTextForm: new Form({
      solution_text: ''
    }),
    openEndedDefaultTextForm: new Form({
      open_ended_default_text: 'Enter text that you would like to appear when your student sees the text submissions area.'
    }),
    textSubmissionForm: new Form({
      text_submission: '',
      assignmentId: null,
      questionId: null
    }),
    uploadFileForm: new Form({
      questionFile: null,
      assignmentId: null,
      questionId: null
    }),
    cutupsForm: new Form({
      chosen_cutups: ''
    }),
    uploadSolutionForm: new Form({
      questionId: null
    }),
    questionPointsForm: new Form({
      points: null
    }),
    questionWeightForm: new Form({
      weight: null
    }),
    openEndedSubmissionTypeAllowed: false,
    openEndedSubmissionType: 'text',
    iframeLoaded: false,
    showedInvalidTechnologyMessage: false,
    showQuestion: true,
    perPage: 1,
    currentPage: 1,
    currentUrl: '',
    currentCutup: 1,
    questions: [],
    initializing: true, // use to show a blank screen until all is loaded
    assignmentId: '',
    name: '',
    questionId: false,
    originalOpenEndedSubmissionType: ''
  }),
  computed: {
    ...mapGetters({
      user: 'auth/user'
    }),
    allFlashcards () {
      if (this.assessmentType !== 'flashcard') return []
      return this.questions
        .map(q => {
          try {
            const parsed = JSON.parse(q.qti_json)
            const card = parsed.card
            const settings_override = q.flashcard_card_settings ? JSON.parse(q.flashcard_card_settings) : null
            // hint lives at the question level (q.hint), but also check inside card for bulk-imported cards
            const hint = q.hint || card.hint || null
            return { ...card, hint, question_id: q.id, student_response: q.student_response || null, settings_override }
          } catch (e) {
            return null
          }
        })
        .filter(Boolean)
    },
    isAdmin: () => window.config.isAdmin,
    isLocalMe: () => window.config.isAdmin && window.location.hostname === 'local.adapt',
    showAutoGradedSubmissionInformation () {
      return (this.questions[this.currentPage - 1].technology === 'qti' || this.questions[this.currentPage - 1].technology_iframe) && !this.isDiscussIt() &&
        this.showSubmissionInformation &&
        this.showQuestion && this.assessmentType !== 'learning tree' && !this.isFormative
    },
    showOpenEndedSubmissionInformation () {
      return (this.isOpenEnded || this.questions[this.currentPage - 1].rubric) &&
        (this.user.role === 3) &&
        (this.showSubmissionInformation || this.openEndedSubmissionType === 'file' || this.questions[this.currentPage - 1].rubric) &&
        !this.isAnonymousUser
    },
    modalSubmissionInformationTitle () {
      if (this.showAutoGradedSubmissionInformation && this.showOpenEndedSubmissionInformation) {
        return 'Submission Information'
      }
      if (this.showAutoGradedSubmissionInformation) {
        return 'Auto-Graded Submission Information'
      }
      return this.getOpenEndedTitle()
    },
    autoGradedSubmissionInformationHeader () {
      return this.showAutoGradedSubmissionInformation && this.showOpenEndedSubmissionInformation
        ? '<h2 class="h7">Auto-Graded Submission Information</h2>' : ''
    },
    openEndedSubmissionInformationHeader () {
      return this.showAutoGradedSubmissionInformation && this.showOpenEndedSubmissionInformation
        ? `<h2 class="h7">${this.getOpenEndedTitle()}</h2>` : ''
    }
  },
  watch: {
    openEndedSubmissionType: function (newVal, oldVal) {
      this.originalOpenEndedSubmissionType = oldVal
    },
    isLoading: function (newVal) {
      if (this.questions[this.currentPage - 1] && this.questions[this.currentPage - 1].technology === 'h5p') {
        this.resizeCacheIndex++
      }
    }
  },
  async created () {
    window.addEventListener('message', this.receiveMessage, false)
    this.doCopy = doCopy
    this.getTechnologySrc = getTechnologySrc
    this.editQuestionSource = editQuestionSource
    this.getQuestionToEdit = getQuestionToEdit
    this.getQuestionRevisionToEdit = getQuestionRevisionToEdit
    this.getCaseStudyNotesByQuestion = getCaseStudyNotesByQuestion
    try {
      this.inIFrame = window.self !== window.top
    } catch (e) {
      this.inIFrame = true
    }
    h5pResizer()
    this.submitUploadFile = submitUploadFile
    this.getAcceptedFileTypes = getAcceptedFileTypes
    this.downloadSolutionFile = downloadSolutionFile
    this.downloadSubmissionFile = downloadSubmissionFile
    this.getFullPdfUrlAtPage = getFullPdfUrlAtPage
    this.updateCompletionSplitOpenEndedSubmissionPercentage = updateCompletionSplitOpenEndedSubmissionPercentage
    window.addEventListener('keydown', this.hotKeys)
    window.addEventListener('visibilitychange', this.visibilityChange)
  },
  destroyed () {
    window.removeEventListener('keydown', this.hotKeys)
    window.removeEventListener('visibilitychange', this.visibilityChange)
  },
  async mounted () {
    if (['0', '1'].includes(localStorage.presentationMode)) {
      this.presentationMode = localStorage.presentationMode === '1'
    } else {
      localStorage.presentationMode = 1
      this.presentationMode = true
    }

    this.clickerApp = window.config.clickerApp
    if (this.isPhone()) {
      this.questionCol = 12
    }
    if (localStorage.ltiTokenId) {
      await this.refreshToken()
    }
    window.addEventListener('resize', this.resizeHandler)
    this.isAnonymousUser = this.user.email === 'anonymous'
    this.isLoading = true

    this.uploadFileType = (this.user.role === 2) ? 'solution' : 'submission' // students upload question submissions and instructors upload solutions
    this.uploadFileUrl = (this.user.role === 2) ? '/api/solution-files' : '/api/submission-files'

    this.assignmentId = this.$route.params.assignmentId

    /// Why do I need the inIFrame???
    if (this.inIFrame && this.user.role === 3) {
      await this.redirectIfBetaCourse()
    }
    if (this.inIFrame && this.user.role === 2) {
      await this.redirectIfBetaCourse()
    }
    this.questionId = this.$route.params.questionId
    this.shownSections = this.$route.params.shownSections
    this.canView = await this.getAssignmentInfo()
    if (!this.canView) {
      this.isLoading = false
      return false
    }
    await this.createLtiLaunchIfNeeded()
    if (this.source === 'a') {
      await this.getSelectedQuestions(this.assignmentId, this.questionId)
      if (!this.questions.length) {
        this.isLoading = false
        return false
      }
      this.showAssignmentStatistics = this.questions.length && (this.user.role === 2 || (this.user.role === 3 && this.students_can_view_assignment_statistics))
      if (this.showAssignmentStatistics) {
        this.getScoresSummary = getScoresSummary
      }
      if (this.questionId) {
        this.currentPage = this.getInitialCurrentPage(this.questionId)
      } else {
        this.questionId = this.questions[0].id
      }
      await this.changePage(this.currentPage)
      if (this.isDiscussIt()) {
        this.$nextTick(() => {
          document.querySelectorAll('.container').forEach(element => {
            element.style.maxWidth = '98%'
          })
          const questionToView = document.getElementById('question-to-view')

          if (questionToView) {
            // Find all elements within 'question-to-view'
            questionToView.querySelectorAll('*').forEach(element => {
              // Remove the classes 'pl-3' and 'pr-3' if they exist
              element.classList.remove('pl-3', 'pr-3')
              element.classList.forEach(className => {
                if (className.startsWith('col')) {
                  // Add 'padding: 0px;' to the element's style
                  element.style.padding = '0px'
                }
              })
            })
          }
        })
      }
      this.testingH5p = this.isLocalMe && true
      if (this.testingH5p) {
        this.questions[this.currentPage - 1].technology_iframe = this.questions[this.currentPage - 1].technology_iframe.replace('https://studio.libretexts.org', 'https://staging.studio.libretexts.org')
      }

      this.hasAtLeastOneSubmission = this.questions[this.currentPage - 1].has_at_least_one_submission
      if (this.inIFrame) {
        this.showSubmissionInformation = this.questions[this.currentPage - 1].submission_information_shown_in_iframe &&
          this.user.role !== 2
        this.showAssignmentInformation = this.questions[this.currentPage - 1].assignment_information_shown_in_iframe
        this.showAttribution = this.questions[this.currentPage - 1].attribution_information_shown_in_iframe
        if (!this.showAssignmentInformation) {
          this.assignmentInformationMarginBottom = 'mb-0'
        }
      }
      if (this.isAnonymousUser || this.isInstructorWithAnonymousView || this.user.role === 5) {
        this.showSubmissionInformation = false
        this.showAssignmentInformation = false
      }
      this.setQuestionCol()
      this.resizeHandler()

      if (this.user.role === 2) {
        await this.getCutups(this.assignmentId)
      }

      this.licenseVersionOptions = this.defaultLicenseVersionOptions
    }
    if (this.assessmentType === 'clicker') {
      this.centrifuge = await initCentrifuge()
      const sub = this.centrifuge.newSubscription(`clicker-status-${this.assignmentId}`)
      const clickerStatusUpdated = this.clickerStatusUpdated
      sub.on('publication', async function (ctx) {
        await clickerStatusUpdated(ctx)
      }).subscribe()

      const sub2 = this.centrifuge.newSubscription(`view-clicker-submissions-${this.assignmentId}`)
      const viewClickerSubmissionsUpdated = this.viewClickerSubmissionsUpdated
      sub2.on('publication', async function (ctx) {
        await viewClickerSubmissionsUpdated(ctx)
      }).subscribe()
    }
    if (this.inIFrame) {
      this.$refs['questionContainer'].classList.remove('container')
      $('.row').removeClass('row')
      $('.col-12').removeClass('col-12')
      $('.container').removeClass('container')
    } else if (this.isPhone() || this.clickerApp) {
      $('.page-title').addClass('ml-2')
      $('#main-content').removeClass('container')
      $('#questionContainer').removeClass('container')
      $('.col-8').removeClass('col-8').addClass('col-12')
      $('.col-4').removeClass('col-4').addClass('col-12')
      $('.sidebar-card').removeClass('sidebar-card')
      $('.row').removeClass('row')
    }
    if (this.user.role === 3) {
      await this.initClickerAssignmentsForEnrolledAndOpenCourses()
    }
    if (this.user.role === 2) {
      // await this.startClickerAssessment()
    }
    this.previouslyUploadedAudioFile = this.questions[this.currentPage - 1].submission ? this.questions[this.currentPage - 1].submission : ''
  },
  beforeDestroy () {
    window.removeEventListener('message', this.receiveMessage)
    window.removeEventListener('resize', this.resizeHandler)
    clearInterval(this.autoSaveInterval)
    window.removeEventListener('beforeunload', this.handleBeforeUnload)
    if (this.reviewQuestionPollingSetInterval) {
      clearInterval(this.reviewQuestionPollingSetInterval)
      this.reviewQuestionPollingSetInterval = null
    }
    try {
      if (this.centrifuge) {
        this.centrifuge.disconnect()
      }
    } catch (error) {
      // won't be a function for all the other ones that haven't been defined on the page
    }
  },
  methods: {
    isQtiOrForgeWithQtiAnswerSolution,
    handleFixCKEditorWithPasteWarning,
    formatFileSize,
    inputFilter,
    inputFile,
    isPhone,
    doCopy,
    updateAutoAttribution,
    getTechnologySrcDoc,
    addGlow,
    hideSubmitButtonsIfCannotSubmit,
    async getWebworkSolution (problemJWT) {
      try {
        const { data } = await axios.get(`/api/webwork/solution/${problemJWT}`)
        if (data.type === 'error') {
          this.$noty.error(data.message)
          return
        }
        if (data.message) {
          this.isLoading = false
          this.showSolutionFileHTML = true
          this.questions[this.currentPage - 1].solution_html = '<h2 class="editable">Solution</h2>' + data.message
          this.$nextTick(() => {
            const el = document.getElementById('solution-html')
            this.convertMathJaxV2ToV3(el)
            this.typesetMath(el)
          })
          this.questions[this.currentPage - 1].solution_type = 'html'
        }
      } catch (error) {
        this.$noty.error(error.message)
      }
    },
    handleBeforeUnload () {
      if (this.isOpenEndedTextSubmission &&
        this.user.role === 3 &&
        !this.isAnonymousUser &&
        this.textSubmissionForm.text_submission &&
        this.submitButtonActive) {
        this.submitText(false)
      }
    },
    cardChanged (questionId) {
      if (this.assessmentType !== 'flashcard' || !questionId) return
      this.currentPage = this.getInitialCurrentPage(questionId)
      this.questionId = +questionId
    },
    isFlashcard () {
      const question = this.questions[this.currentPage - 1]
      try {
        return question.technology === 'qti' &&
          JSON.parse(question.qti_json).questionType === 'flashcard'
      } catch (e) {
      }
      return false
    },
    getRemoveForgeQuestionMessage (question) {
      return this.forgeDraftExists(question) ? 'Please first remove all drafts from the Forge Settings.' : 'Remove question from assignment.'
    },
    forgeDraftExists (question) {
      const parentForgeQuestionId = question.id
      return this.questions.filter(
        question => question.forge_source_id === parentForgeQuestionId
      ).length > 0
    },
    addDraft (currentPage) {
      setTimeout(() => {
        const currentQuestionId = this.questions[currentPage - 1].id
        window.location.href = `/assignments/${this.assignmentId}/questions/view/${currentQuestionId}`
      }, 1500)
    },
    async onDraftsDeleted (deletedQuestionIds) {
      // Check if current question was deleted
      const currentQuestionId = this.questions[this.currentPage - 1].id
      const currentWasDeleted = deletedQuestionIds.includes(currentQuestionId)

      // Remove deleted questions from the array
      this.questions = this.questions.filter(q => !deletedQuestionIds.includes(q.id))

      // If current question was deleted, navigate to previous or first question
      if (currentWasDeleted) {
        if (this.currentPage > 1) {
          this.currentPage = this.currentPage - 1
        } else {
          this.currentPage = 1
        }
      } else if (this.currentPage > this.questions.length) {
        // Current page is now out of bounds
        this.currentPage = this.questions.length
      }

      // Reload the current page
      await this.changePage(this.currentPage)
    },
    downloadAttachmentUrl (attachment, questionId) {
      return `/api/questions/download-attachment/assignment/${this.assignmentId}/question/${questionId}/s3-key/${attachment.s3_key}`
    },
    getQuestionStatusClass (border = false) {
      let questionStatusClass = ''
      switch (this.questionStatus) {
        case ('open'):
          questionStatusClass = 'text-success'
          break
        case ('closed'):
          questionStatusClass = 'text-danger'
          break
        case ('late'):
          questionStatusClass = 'text-warning'
          break
      }
      if (border) {
        questionStatusClass = questionStatusClass.replace('text-', '')
      }
      return questionStatusClass
    },
    keepPastedContent () {
      this.pastedContent = true
      this.$bvModal.hide('modal-confirm-paste-into-ckeditor')
    },
    startAgain () {
      this.textSubmissionForm.text_submission = ''
      this.ckeditorTextSubmissionKey++
      this.$bvModal.hide('modal-confirm-paste-into-ckeditor')
      this.pastedContent = false
    },
    revertToOriginal (type) {
      switch (type) {
        case ('multiple_answers'):
          const el = document.querySelector('#question-to-view #original-response')
          if (!el) return
          const originalIds = JSON.parse(el.dataset.ids || '[]')
          let qtiJson = JSON.parse(this.qtiJson)
          qtiJson.studentResponse = originalIds
          this.questions[this.currentPage - 1].qti_json = JSON.stringify(qtiJson)
          break
      }
    },
    async submitAudio (filename) {
      try {
        const { data } = await axios.post(`/api/submission-audios/${this.assignmentId}/${this.questions[this.currentPage - 1].id}/1/${filename}`)
        this.submittedAudioUpload({ data: data })
        this.previouslyUploadedAudioFile = filename
      } catch (error) {
        this.$noty.error(error.message)
      }
    },
    viewSubmissions () {
      this.$bvModal.show('modal-instructor-clicker-question')
      this.selectedClickerViewOption = 'submissions'
      this.submissionsView = true
    },
    getPageStyle (page, active, clickerStatus) {
      if (this.assessmentType !== 'clicker') {
        return {} // default
      }

      let color = clickerStatus === 'show_open' ? 'green' : 'red'
      return {
        color,
        backgroundColor: active ? 'transparent' : '',
        boxShadow: 'none'
      }
    },
    updateSelectedClickerViewOption () {
      this.defaultSubmissionResultsKey++
      this.$forceUpdate()
      this.$nextTick(() => {
        this.renderMathJax()
      })
    },
    async updateShowSolutionForClosedQuestion () {
      try {
        const { data } = await axios.patch(`/api/assignments/${this.assignmentId}/questions/${this.questions[this.currentPage - 1].id}/release-solution-when-question-is-closed`)
        this.$noty[data.type](data.message)
      } catch (error) {
        this.$noty.error(error.message)
      }
    },
    clickerHasSubmissionsToShow (question) {
      return question.technology === 'webwork' ||
        ['True/False Question', 'Multiple Choice'].includes(question.h5p_type) ||
        ['true_false', 'multiple_choice'].includes(question.qti_json_type)
    },
    showClickerViewOption (canViewClickerSubmissions, question, value) {
      return canViewClickerSubmissions && (value !== 'submissions' || this.clickerHasSubmissionsToShow(question))
    },
    async updateClickerTimeToSubmit () {
      try {
        const { data } = await this.clickerTimeForm.patch(`/api/assignments/${this.assignmentId}/questions/${this.questions[this.currentPage - 1].id}/custom-clicker-time-to-submit`)
        this.$noty[data.type](data.message)
        if (data.type === 'success') {
          this.questions[this.currentPage - 1].time_to_submit = this.clickerTimeForm.time_to_submit
        }
      } catch (error) {
        if (error.message.includes('status code 422')) {
          this.$nextTick(() => fixInvalid())
          this.allFormErrors = this.clickerTimeForm.errors.flatten()
          this.$bvModal.show('modal-form-errors-clicker-time-to-submit-error-form')
        }
      }
    },
    async openQuestion () {
      if (this.solutionsReleased) {
        this.$noty.info('Please unrelease the solutions to this assignment so that you can open this question.')
        return false
      }
      try {
        const { data } = await this.clickerTimeForm.post(`/api/assignments/${this.assignmentId}/questions/${this.questions[this.currentPage - 1].id}/open-clicker`)
        if (data.type === 'error') {
          this.$noty.error(data.message)
        } else {
          this.canViewClickerSubmissions = false
          this.clickerViewIsSubmissions = true
          this.clickerAnswerShown = false
          this.selectedClickerViewOption = 'question'
        }
      } catch (error) {
        this.$noty.error(error.message)
      }
    },
    async resetQuestion () {
      try {
        const { data } = await axios.post(`/api/assignments/${this.assignmentId}/questions/${this.questions[this.currentPage - 1].id}/reset-clicker`)
        this.$noty[data.type](data.message)
        if (data.type !== 'error') {
          this.questions[this.currentPage - 1].clicker_status = this.clickerStatus = 'show_open'
          this.canViewClickerSubmissions = false
          this.clickerViewIsSubmissions = true
          this.clickerAnswerShown = false
        }
      } catch (error) {
        this.$noty.error(error.message)
      }
    },
    async closeInstructorClickerQuestionModal () {
      this.$bvModal.hide('modal-instructor-clicker-question')
      await this.closePoll(this.instructorClosedPollMessage)
    },
    triggerFileSelect () {
      document.querySelector('#file').click()
    },
    async pauseClicker () {
      this.clickerPaused = true
      try {
        const { data } = await axios.patch(`/api/assignments/${this.assignmentId}/questions/${this.questions[this.currentPage - 1].id}/pause-clicker`)
        if (data.type === 'error') {
          this.$noty.error(data.message)
        }
      } catch (error) {
        this.$noty.error(error.message)
      }
    },
    async resumeClicker () {
      try {
        const { data } = await axios.patch(`/api/assignments/${this.assignmentId}/questions/${this.questions[this.currentPage - 1].id}/resume-clicker`,
          { current_time_left: this.currentTimeLeft })
        if (data.type === 'error') {
          this.$noty.error(data.message)
        }
      } catch (error) {
        this.$noty.error(error.message)
      }
    },
    resetClickerAssignmentIdClickerQuestionId,
    initClickerAssignmentsForEnrolledAndOpenCourses,
    async addTimeToClicker () {
      try {
        const { data } = await axios.post(`/api/assignments/${this.assignmentId}/questions/${this.questions[this.currentPage - 1].id}/add-time`,
          {
            time_to_add: 15,
            time_left: parseInt(this.currentTimeLeft) / 1000
          })
        if (data.type === 'error') {
          this.$noty.error(data.message)
        }
      } catch (error) {
        this.$noty.error(error.message)
      }
    },
    cleanUpInstructorClickerView () {
      if (this.user.role === 2) {
        this.$nextTick(() => {
          const iframe = document.getElementById(`clicker-technology-iframe-${this.currentPage}-${this.cacheIndex}`)
          switch (this.questions[this.currentPage - 1].technology) {
            case ('h5p'):
              let cssUpdates = h5pOnLoadCssUpdates
              cssUpdates.elements.push({
                selector: '.h5p-actions',
                style: 'display:none;'
              })
              iframe.contentWindow.postMessage(JSON.stringify(cssUpdates), '*')
              break
            case ('webwork'):
              iframe.contentWindow.postMessage(JSON.stringify({
                elements: [{
                  selector: 'input[name="submitAnswers"]',
                  style: 'display:none'
                }]
              }), '*')
              break
          }
        })
      }
    },
    isLocked () {
      return this.hasAtLeastOneSubmission &&
        !this.presentationMode &&
        !this.inIFrame &&
        !this.isLoading &&
        this.user.role === 2 &&
        !this.isInstructorWithAnonymousView &&
        !this.isFormative &&
        !this.clickerApp
    },
    async initUpdateUseExistingRubric (useExistingRubric) {
      this.useExistingRubric = useExistingRubric.value
      this.rubricPointsBreakDownExists = false
      try {
        const { data } = await axios.get(`/api/rubric-points-breakdown/assignment/${this.assignmentId}/question/${this.questions[this.currentPage - 1].id}/exists`)
        if (data.type === 'error') {
          this.$noty.error(data.message)
        }
        this.rubricPointsBreakDownExists = data.rubric_points_breakdown_exists
      } catch (error) {
        this.$noty.error(error.message)
      }
      if (this.rubricPointsBreakDownExists) {
        this.$bvModal.show('modal-confirm-update-use-existing-rubric')
      } else {
        await this.updateUseExistingRubric()
      }
    },
    async updateUseExistingRubric () {
      this.$bvModal.hide('modal-confirm-update-use-existing-rubric')
      try {
        const { data } = await axios.patch(`/api/assignments/${this.assignmentId}/questions/${this.questions[this.currentPage - 1].id}/use-existing-rubric`)
        if (data.type === 'info') {
          this.questions[this.currentPage - 1].use_existing_rubric = this.useExistingRubric
        }
        this.$noty[data.type](data.message)
      } catch (error) {
        this.$noty.error(error.message)
      }
    },
    async initConfirmDeleteOverridingRubric () {
      this.rubricPointsBreakDownExists = false
      try {
        const { data } = await axios.get(`/api/rubric-points-breakdown/assignment/${this.assignmentId}/question/${this.questions[this.currentPage - 1].id}/exists`)
        if (data.type === 'error') {
          this.$noty.error(data.message)
        }
        this.rubricPointsBreakDownExists = data.rubric_points_breakdown_exists
      } catch (error) {
        this.$noty.error(error.message)
      }
      this.$bvModal.show('modal-confirm-delete-overriding-rubric')
    },
    async deleteOverridingRubric () {
      try {
        const { data } = await axios.delete(`/api/assignments/${this.assignmentId}/questions/${this.questions[this.currentPage - 1].id}/delete-custom-rubric`)
        this.$noty[data.type](data.message)
        if (data.type !== 'error') {
          this.questions[this.currentPage - 1].rubric = data.rubric
          this.questions[this.currentPage - 1].overriding_rubric = null
          this.$bvModal.hide('modal-confirm-delete-overriding-rubric')
        }
      } catch (error) {
        this.$noty.error(error.message)
      }
    },
    async setCustomRubric (customRubric) {
      try {
        const { data } = await axios.patch(`/api/assignments/${this.assignmentId}/questions/${this.questions[this.currentPage - 1].id}/update-custom-rubric`,
          { custom_rubric: customRubric, question_points: this.questions[this.currentPage - 1].points })
        if (data.type !== 'info') {
          this.$noty[data.type](data.message)
        }
        if (data.type !== 'error') {
          console.error(customRubric)
          this.questions[this.currentPage - 1].rubric = customRubric
          this.questions[this.currentPage - 1].overriding_rubric = true
          this.questions[this.currentPage - 1].use_existing_rubric = false
          // console.error(customRubric)
          this.showRubricProperties = false
        }
      } catch (error) {
        this.$noty.error(error.message)
      }
    },
    async createLtiLaunchIfNeeded () {
      if (this.user.role === 3 && !this.user.fake_student) {
        try {
          const { data } = await axios.post(`/api/lti-launch/assignment/${this.assignmentId}/create-lti-launch-if-needed`)
          if (data.redirect) {
            location.href = data.redirect
          }
          data.type === 'success'
            ? console.log(data.message) : console.error(data.message)
        } catch (error) {
          console.error(error.message)
        }
      }
    },
    async contactInstructorToReleaseAssignment () {
      try {
        const { data } = await axios.post(`/api/assignments/${this.assignmentId}/contact-instructor-to-release-assignment`)
        this.$noty[data.type](data.message)
      } catch (error) {
        this.$noty.error(error.message)
      }
    },
    updateSubmittedWork (data) {
      this.questions[this.currentPage - 1].submitted_work = data.submittedWorkUrl ? data.submittedWorkUrl : ''
      this.questions[this.currentPage - 1].submitted_work_at = data.submittedWorkAt ? data.submittedWorkAt : ''
    },
    async updateCanSubmitWorkOverride () {
      const newValue = 1 - this.questions[this.currentPage - 1].can_submit_work
      try {
        const { data } = await axios.patch(`/api/assignments/${this.assignmentId}/questions/${this.questions[this.currentPage - 1].id}/can-submit-work-override/${newValue}`)
        this.$noty[data.type](data.message)
        if (data.type === 'error') {
          this.questions[this.currentPage - 1].can_submit_work = 1 - this.questions[this.currentPage - 1].can_submit_work
        }
      } catch (error) {
        this.questions[this.currentPage - 1].can_submit_work = 1 - this.questions[this.currentPage - 1].can_submit_work
      }
    },
    cleanHint () {
      if (this.questions[this.currentPage - 1].hint) {
        this.questions[this.currentPage - 1].hint = this.questions[this.currentPage - 1].hint.replace('<h2 class="editable">Hint</h2>', '')
      }
    },
    showHintModal () {
      if (this.questions[this.currentPage - 1].shown_hint) {
        this.cleanHint()
      }
      !this.questions[this.currentPage - 1].shown_hint
        ? this.$bvModal.show('modal-confirm-show-hint')
        : this.$bvModal.show('modal-hint')

      this.$nextTick(() => {
        this.typesetMath(document.getElementById('hint-html'))
      })
    },
    isDiscussIt () {
      const question = this.questions[this.currentPage - 1]
      try {
        return question.technology === 'qti' &&
          JSON.parse(question.qti_json).questionType === 'discuss_it'
      } catch (error) {

      }
      return false
    },
    isForge () {
      const question = this.questions[this.currentPage - 1]
      try {
        return question.technology === 'qti' &&
          ['forge', 'forge_iteration'].includes(JSON.parse(question.qti_json).questionType)
      } catch (error) {

      }
      return false
    },
    async confirmGiveUp () {
      try {
        const { data } = await axios.get(`/api/can-give-up/assignments/${this.assignmentId}/questions/${this.questions[this.currentPage - 1].id}/validate`)
        if (data.type === 'success') {
          this.$bvModal.show('modal-confirm-give-up')
        } else {
          this.$noty.info(data.message)
        }
      } catch (error) {
        console.error(error.message)
      }
    },
    togglePresentationMode (presentationMode) {
      localStorage.presentationMode = presentationMode.value ? 1 : 0
      this.presentationMode = !this.presentationMode
      this.clickerViewIsSubmissions = false
      this.renderMathJax()
    },
    async toggleShowClickerAnswer () {
      this.clickerAnswerShown = !this.clickerAnswerShown
      await this.$nextTick(() => {
          this.viewClickerSubmissions(this.clickerAnswerShown)
        }
      )
      if (!this.questions[this.currentPage - 1].qti_json || ['multiple_choice', 'true_false'].includes(this.questions[this.currentPage - 1].qti_json_type)) {
        this.clickerAnswerShownText = this.clickerAnswerShown ? 'Hide' : 'Show'
      }
    },
    async closePoll (message = '') {
      await this.endClickerAssessment(message)
      this.clickerPaused = false
    },
    movePageByArrow (newPage) {
      window.location = `/assignments/${this.assignmentId}/questions/view/${this.questions[newPage - 1].id}`
    },
    updateToLatestRevision () {
      if (this.questions[this.currentPage - 1].pending_question_revision === null) {
        this.$noty.info('We are unable to locate the question revision.  Please contact support for assistance.')
        return false
      }
      this.$bvModal.show('modal-show-revision')
    },
    async showLatestRevision () {
      try {
        const { data } = await axios.get(`/api/pending-question-revisions/${this.questions[this.currentPage - 1].question_revision_id_latest}`)
        if (data.type === 'error') {
          this.$noty.error(data.message)
          return false
        }
        this.questions[this.currentPage - 1].pending_question_revision = data.question_revision
        this.pendingQuestionRevision = data.question_revision
        this.$bvModal.show('modal-show-revision')
      } catch (error) {
        this.$noty.error(error.message)
      }
    },
    async setCurrentPage (ctx) {
      console.log('set-current-page')
      console.log(ctx)
      const data = ctx.data
      if (data.assignment_id === +this.assignmentId && data.question_id !== +this.questions[this.currentPage - 1].id) {
        window.location.href = `/assignments/${this.assignmentId}/questions/view/${data.question_id}`
      }
    },
    async viewClickerSubmissionsUpdated (ctx) {
      const data = ctx.data
      if (data.question_id === +this.questions[this.currentPage - 1].id && data.assignment_id === +this.assignmentId) {
        console.error('Clicker submissions shown')
        if (data.view_clicker_submissions) {
          if (!this.justLoadedPage) {
            this.selectedClickerViewOption = this.clickerHasSubmissionsToShow(this.questions[this.currentPage - 1]) ? 'submissions' : 'scores'
          }
          this.justLoadedPage = false
          this.clickerAnswerShown = data.show_answer
          await this.closePoll(this.instructorClosedPollMessage)
          await this.getClickerSubmissions(data.question_id)
        }
        if (data.show_answer) {
          console.error('Clicker submissions shown')
          console.error(data.qti_answer_json)
        }
        if (data.qti_answer_json) {
          this.questions[this.currentPage - 1].qti_answer_json = data.qti_answer_json
          this.$forceUpdate()
          this.$nextTick(() => {
            this.$bvModal.show(`qti-answer-${this.questions[this.currentPage - 1].id}`)
          })
        }
      }
    },
    async clickerStatusUpdated (ctx) {
      const data = ctx.data
      console.error('clicker status updated')
      console.error(data)
      if (data.assignment_id === +this.assignmentId && data.question_id === +this.questions[this.currentPage - 1].id) {
        if (data.reload_student_view && this.user.role === 3) {
          location.reload()
        }
        console.error('updating clicker status')
        if (data.status === 'view_and_not_submit') {
          this.clickerViewOptions = [
            { text: 'Question', value: 'question' },
            { text: 'Submissions', value: 'submissions' },
            { text: 'Scores', value: 'scores' }
          ]
        }
        if (data.status === 'view_and_not_submit' &&
          (data.show_solution_radio_button || this.user.role === 2) &&
          !this.clickerViewOptions.find(item => item.value === 'solution')) {
          if (this.user.role === 3) {
            this.questions[this.currentPage - 1].solution_html = data.solution_html ? atob(data.solution_html) : ''
            this.questions[this.currentPage - 1].qti_answer_json = data.qti_answer_json ? atob(data.qti_answer_json) : ''
            this.$nextTick(() => {
              this.renderMathJax()
            })
          }
          if (this.questions[this.currentPage - 1].solution_html || this.questions[this.currentPage - 1].qti_answer_json) {
            this.clickerViewOptions.push({ text: 'Solution', value: 'solution' })
          }
        }
        if (data.status !== 'paused' && data.status !== 'resumed') {
          this.timeLeft = data.time_left
        }
        if (data.current_time_left) {
          this.currentTimeLeft = data.current_time_left
          this.timeLeft = data.current_time_left
        }
        this.countDownKey++
        await this.canSubmit()
        this.updateClickerMessage(data.status, data.message)
      } else {
        this.clickerAssignmentId = +data.assignment_id
        this.clickerQuestionId = data.question_id
      }
    },
    reloadAndRemoveQuestionEditorUpdatedAt () {
      this.$emit('reloadCurrentAssignmentQuestions')
      clearInterval(window.currentQuestionEditorUpdatedAt)
    },
    async submitRemoveQuestion (questionId = null) {
      if (!questionId) {
        questionId = this.questions[this.currentPage - 1].id
      }
      try {
        const { data } = await axios.delete(`/api/assignments/${this.assignmentId}/questions/${questionId}`)
        this.$bvModal.hide('modal-remove-question')
        if (data.type === 'error') {
          this.$noty.error(data.message)
          return false
        }
        this.questions = this.questions.filter(question => question.id !== questionId)
        this.$noty.info(data.message)
        if (this.currentPage > 1) {
          this.currentPage--
        } else {
          this.currentPage = 1
        }
        if (this.questions.length) {
          await this.changePage(this.currentPage)
        }
      } catch (error) {
        this.$noty.error('We could not remove the question from the assignment.  Please try again or contact us for assistance.')
      }
      this.$bvModal.hide('modal-confirm-delete-draft')
    },
    openRemoveQuestionModal (question) {
      if (question.forge_source_id) {
        this.$noty.info('You can delete this draft by opening up the Forge Settings')
        return
      }
      if (this.isBetaAssignment) {
        this.$bvModal.show('modal-cannot-delete-assessment-from-beta-assignment')
        return false
      }
      if (this.forgeDraftExists(question)) {
        this.$noty.info('Please first remove all drafts by opening the Forge Settings.')
        return
      }
      this.$bvModal.show('modal-remove-question')
    },
    canEarnLearningTreeReset () {
      return this.questions[this.currentPage - 1].number_of_learning_tree_paths - this.questions[this.currentPage - 1].number_resets_available >= this.questions[this.currentPage - 1].number_of_successful_paths_for_a_reset
    },
    async resetRootNodeSubmission () {
      try {
        const { data } = await axios.post(`/api/learning-tree-node/reset-root-node-submission/assignment/${this.assignmentId}/question/${this.questions[this.currentPage - 1].id}`)
        this.$noty[data.type](data.message)
        if (data.type !== 'error') {
          await this.reloadSingleQuestion()
          this.$bvModal.hide('modal-reset-root-node-submission')
        }
      } catch (error) {
        this.$noty.error(error.message)
      }
    },
    enterLearningTree () {
      this.$bvModal.hide('modal-submission-accepted')
      this.$bvModal.show('modal-learning-tree')
    },
    processReceiveMessage,
    receiveMessage (event) {
      if (event.data === 'Close learning tree modal') {
        this.$bvModal.hide('modal-learning-tree')
        $('button.close').click()
        return false
      }
      let vm = this
      console.log(this.$route.name)
      this.processReceiveMessage(vm, this.$route.name, event)
    },
    increaseLearningTreeModalSize () {
      this.$nextTick(() => {
        $('.modal-dialog.modal-xl').css('max-width', '95%')
      })
    },
    async viewLatestRevision () {
      this.processingUpdatingQuestionView = true
      try {
        const { data } = await axios.get(`/api/questions/${this.questions[this.currentPage - 1].id}`)
        if (data.type === 'success') {
          let originalQuestionRevisionId = this.questions[this.currentPage - 1].question_revision_id
          // known issue is that the webwork solution won't load.  Need to go into the DOM
          // hideSubmitButtonsIfCannotSubmit (technology, updatedLastSubmittedInfo = false) maybe
          for (const property in data['question']) {
            this.questions[this.currentPage - 1][property] = data['question'][property]
          }
          if (this.questions[this.currentPage - 1].rubric_categories.length) {
            this.questions[this.currentPage - 1].question_type = 'report'
          }
          this.questions[this.currentPage - 1].viewing_latest_revision = true
          if (this.questions[this.currentPage - 1].solution_html) {
            this.questions[this.currentPage - 1].solution_type = 'html'
          }
          if (this.questions[this.currentPage - 1].solution_file_url) {
            this.questions[this.currentPage - 1].solution_type = 'q'
          }
          this.questions[this.currentPage - 1].question_revision_id_original = originalQuestionRevisionId
          this.questions[this.currentPage - 1].technology_iframe = data['question'].technology_iframe_src
          this.cacheKey++
          this.reportCacheKey++
          await this.changePage(this.currentPage)
          this.$noty.info('The question has been updated to the latest revision.')
        }
      } catch (error) {
        this.$noty.error(error.message)
      }
      this.processingUpdatingQuestionView = false
    },
    async viewCurrentRevision () {
      this.processingUpdatingQuestionView = true
      this.questionId = this.questions[this.currentPage - 1].id
      await this.getSelectedQuestions(this.assignmentId, this.questions[this.currentPage - 1].id)
      await this.changePage(this.currentPage)
      this.$noty.info('The question has been updated to the current revision.')
      this.processingUpdatingQuestionView = false
    },
    uniqueId,
    hideModalSubmissionAccepted () {
      this.modalSubmissionAcceptedTitle = 'Submission Accepted'
      this.saveSubmissionConfirmation()
    },
    async showSubmissionArray () {
      if (this.questions[this.currentPage - 1].qti_json_type === 'marker') {
        this.$bvModal.show('modal-submission-accepted')
      } else {
        try {
          const { data } = await axios.post(`/api/submissions/submission-array/assignment/${this.assignmentId}/question/${this.questions[this.currentPage - 1].id}`,
            { user_id: this.user.id, submission_history_id: 0 })
          if (data.type === 'error') {
            this.$noty.error(data.message)
          } else {
            this.questions[this.currentPage - 1].submission_array = data.submission_array
            this.submissionArray = data.submission_array
            console.log(this.questions[this.currentPage - 1].submission_array)
            this.completedAllAssignmentQuestions = false
            this.modalSubmissionAcceptedTitle = 'Submission Summary'
            this.$bvModal.show('modal-submission-accepted')
            this.renderMathJax()
          }
        } catch (error) {
          this.$noty.error(error.message)
        }
      }
    },
    setQuestionRevision (revision) {
      console.log('setting revision')
      console.log(this.questionToEdit)
      this.getQuestionRevisionToEdit(revision)
    },
    updateCustomQuestionTitle (newTitle) {
      this.questions[this.currentPage - 1].title = newTitle
    },
    cancelWebworkSubmission () {
      this.$bvModal.hide('modal-confirm-submission')
      this.$noty.info('Your submission has not been saved.')
    },
    async initConfirmSubmission () {
      try {
        const { data } = await axios.get(`/api/unconfirmed-submissions/assignment/${this.assignmentId}/question/${this.questions[this.currentPage - 1].id}`)
        if (data.type === 'error') {
          this.$noty.error(data.message)
        } else {
          this.hasHTML = data.has_html
          this.unconfirmedSubmission = data.unconfirmed_submission
          // this.$bvModal.show('modal-confirm-submission')
          await this.completeSubmission()
        }
      } catch (error) {
        this.$noty.error(error.message)
      }
    },
    async completeSubmission () {
      this.$bvModal.hide('modal-confirm-submission')
      try {
        const { data } = await axios.post(`/api/unconfirmed-submissions/assignment/${this.assignmentId}/question/${this.questions[this.currentPage - 1].id}/store-submission`)
        await this.showResponse(data)
      } catch (error) {
        this.$noty.error(error.message)
      }
    },
    renderMathJax () {
      this.typesetMath(document.getElementById('modal-confirm-submission'))
    },
    showContactInstructorAutoGraded () {
      let canContactInstructorAutoGraded
      canContactInstructorAutoGraded = false
      switch (this.canContactInstructorAutoGraded) {
        case ('always'):
          canContactInstructorAutoGraded = true
          break
        case ('before submission'):
          canContactInstructorAutoGraded = this.questions &&
            ['webwork', 'qti', 'h5p', 'imathas'].includes(this.questions[this.currentPage - 1].technology) &&
            this.questions[this.currentPage - 1].last_submitted === 'N/A'
          break
        case ('before due date'):
          canContactInstructorAutoGraded = !this.pastDue
          break
        case ('after due date'):
          canContactInstructorAutoGraded = this.pastDue
          break
        case ('never'):
          canContactInstructorAutoGraded = false
          break
      }
      return canContactInstructorAutoGraded
    },
    showContactGrader () {
      return this.canContactGrader
    },
    getAdaptId () {
      let adaptId = ''
      if (this.user.role !== 3 && this.questions.length && !this.isLoading) {
        return `${this.assignmentId}-${this.questions[this.currentPage - 1].id}`
      }
      return adaptId
    },
    getLearningTreeId () {
      let learningTreeId = ''
      if (this.user.role !== 3 && this.questions.length && !this.isLoading && this.questions[this.currentPage - 1].learning_tree_id) {
        return `${this.questions[this.currentPage - 1].learning_tree_id}`
      }
      return learningTreeId
    },
    async canSubmit () {
      try {
        const isForge = +this.isForge()
        const { data } = await axios.get(`/api/submissions/can-submit/assignment/${this.assignmentId}/question/${this.questions[this.currentPage - 1].id}/is-forge/${isForge}`)
        if (data.type === 'error') {
          console.log(`Cannot submit: ${data.message}`)
        }
        this.submitButtonActive = data.type === 'success'
        if (this.submitButtonActive && this.questionStatus !== 'late' || !this.submitButtonActive) {
          this.questionStatus = this.submitButtonActive ? 'open' : 'closed'
        }
        this.canViewHint = this.submitButtonActive
        if (['h5p', 'webwork'].includes(this.questions[this.currentPage - 1]['technology'])) {
          let vm = this
          console.error('checking webwork for the can submit thing')
          await this.hideSubmitButtonsIfCannotSubmit(vm, 'questions.view', this.questions[this.currentPage - 1]['technology'], true)
        }
      } catch (error) {
        console.log(error.message)
      }
    },
    async saveSubmissionConfirmation () {
      if (this.completedAllAssignmentQuestions) {
        this.$bvModal.show('modal-assignment-completed')
      }
      try {
        const { data } = await axios.post(`/api/submission-confirmations/assignment/${this.assignmentId}/question/${this.questions[this.currentPage - 1].id}`)
        if (data.type === 'error') {
          console.error(`Error saving submission confirmation: ${data.message}`)
        }
      } catch (error) {
        console.error(`Error saving submission confirmation: ${error.message}`)
      }
    },
    showHideLeftAndRightColumns (showLeftColumn, showRightColumn) {
      this.$root.$emit('bv::hide::tooltip')
      this.$nextTick(() => {
          this.showLeftColumn = showLeftColumn
          this.showRightColumn = showRightColumn
          this.questionCol = this.showLeftColumn && !this.showRightColumn ? 12 : 8
        }
      )
    },
    async initReviewQuestionTimeSpent () {
      if (this.inIFrame) {
        // need to work out this logic with Yasin
        return
      }
      try {
        if (localStorage.reviewQuestionTimeSpent) {
          const reviewQuestionTimeSpent = JSON.parse(localStorage.reviewQuestionTimeSpent)
          const { data } = await axios.patch(`/api/review-history/assignment/${reviewQuestionTimeSpent.assignmentId}/question/${this.questions[this.currentPage - 1].id}`, {
            reviewSessionId: reviewQuestionTimeSpent.reviewSessionId,
            start: reviewQuestionTimeSpent.start,
            end: reviewQuestionTimeSpent.end
          })
          console.log(data)
          localStorage.removeItem('reviewQuestionTimeSpent')
        }
      } catch (error) {
        console.error(error.message)
      }
      if (this.reviewQuestionPollingSetInterval) {
        clearInterval(this.reviewQuestionPollingSetInterval)
      }
      const reviewQuestionTimeSpent = {
        assignmentId: this.assignmentId,
        questionId: this.questionId,
        reviewSessionId: uuidv4(),
        start: this.$moment(),
        end: this.$moment()
      }
      console.log(reviewQuestionTimeSpent)
      localStorage.reviewQuestionTimeSpent = JSON.stringify(reviewQuestionTimeSpent)
      this.reviewQuestionPollingSetInterval = setInterval(() => {
        this.updateReviewQuestionTime()
      }, 3000)
    },
    updateTotalTimeInTaskInactive () {
      let endTime = performance.now()
      let timeDiff = endTime - this.startTimeTaskInactive

      timeDiff /= 1000
      let seconds = Math.round(timeDiff)
      this.totalTimeInTaskInactive += seconds
      console.log(`update total time task inactive: ${this.totalTimeInTaskInactive}`)
    },
    clearReviewQuestionTimeSpentInterval () {
      console.log('clear review time spent interval')
      clearInterval(this.reviewQuestionPollingSetInterval)
      this.reviewQuestionPollingSetInterval = null
    },
    initStartTimeInactive () {
      console.log('init time start inactive')
      this.startTimeTaskInactive = performance.now()
    },
    visibilityChange () {
      if (this.user.role === 3) {
        switch (document.visibilityState) {
          case ('visible'):
            this.pastDue ? this.initReviewQuestionTimeSpent() : this.updateTotalTimeInTaskInactive()
            break
          case ('hidden'):
            this.pastDue ? this.clearReviewQuestionTimeSpentInterval() : this.initStartTimeInactive()
            break
        }
      }
    },
    formatA11YQuestionHtml (a11yQuestionHTML) {
      return a11yQuestionHTML.replace('<div class="mt-section"><h2 class="editable">Text Question</h2>', '').replace('</div>', '')
    },
    async getH5pVideoInteractionSubmissions () {
      try {
        const { data } = await axios.get(`/api/h5p-activity-set/submissions/assignment/${this.assignmentId}/question/${this.questions[this.currentPage - 1].id}`)
        if (data.type === 'error') {
          this.$noty.error(data.message)
          return false
        }
        let hasH5pVideoInteractionSubmissions = data.h5p_activity_set_submissions.length > 0
        this.questions[this.currentPage - 1].has_h5p_activity_set_submissions = hasH5pVideoInteractionSubmissions
        if (hasH5pVideoInteractionSubmissions) {
          let h5pVideoInteractionSubmissions = []
          for (let i = 0; i < data.h5p_activity_set_submissions.length; i++) {
            let question
            let submission = JSON.parse(data.h5p_activity_set_submissions[i].submission)
            try {
              question = submission.object.definition.description['en-US']
              let submissionResultsResponses
              console.log(submission.result.response)
              if (submission.result.response.includes('[,]')) {
                submissionResultsResponses = submission.result.response.split('[,]')
                console.log(submissionResultsResponses)
              } else {
                submissionResultsResponses = [submission.result.response]
              }
              let responses = []
              for (let j = 0; j < submissionResultsResponses.length; j++) {
                responses.push(submission.object.definition.choices.find(choice => parseInt(choice.id) === parseInt(submissionResultsResponses[j])).description['en-US'])
              }

              h5pVideoInteractionSubmissions.push({ question: question, response: responses.join('<br>') })
            } catch (error) {
              console.log('Error processing H5P response')
              h5pVideoInteractionSubmissions.push({ question: question, response: submission.result.response })
              console.log(submission)
            }
          }
          this.questions[this.currentPage - 1].h5p_activity_set_submissions = h5pVideoInteractionSubmissions
          console.log(h5pVideoInteractionSubmissions)
        }

        this.$forceUpdate()
      } catch (error) {
        this.$noty.error(error.message)
      }
    },
    getNumberOfAttemptsAttempts () {
      return parseInt(this.numberOfAllowedAttempts) - parseInt(this.questions[this.currentPage - 1].submission_count)
    },
    async refreshToken () {
      try {
        const { data } = await axios.post('/api/refresh-token', { token: localStorage.ltiTokenId })
        // Save the token.
        localStorage.removeItem('ltiTokenId')
        if (data.type !== 'success') {
          this.$noty.error('Could not refresh token')
          return false
        }
        await this.$store.dispatch('auth/saveToken', {
          token: data.new_token,
          remember: false
        })
      } catch (error) {
        this.$noty.error(error.message)
      }
      localStorage.removeItem('ltiTokenId')
    },
    async resetSubmission () {
      try {
        const { data } = await axios.patch(`/api/submissions/assignments/${this.assignmentId}/question/${this.questions[this.currentPage - 1].id}/reset-submission`)
        this.$noty[data.type](data.message)
        if (data.type === 'info') {
          setTimeout(() => {
            window.location.href = `/assignments/${this.assignmentId}/questions/view/${this.questions[this.currentPage - 1].id}`
          }, 2000)
        }
      } catch (error) {
        this.$noty.error(error.message)
      }
    },
    async handleShownHint () {
      try {
        const { data } = await axios.post(`/api/shown-hints/assignments/${this.assignmentId}/question/${this.questions[this.currentPage - 1].id}`)
        if (data.type === 'error') {
          this.$noty.error(data.message)
          return false
        }
        this.questions[this.currentPage - 1].shown_hint = true
        this.questions[this.currentPage - 1].hint = data.hint
        this.cleanHint()
        this.$bvModal.hide('modal-confirm-show-hint')
        this.$bvModal.show('modal-hint')

        this.$nextTick(() => {
          this.typesetMath(document.getElementById('hint-html'))
        })
        this.maximumNumberOfPointsPossible = this.getMaximumNumberOfPointsPossible()
      } catch (error) {
        this.$noty.error(error.message)
      }
    },
    editLearningTree (learningTreeId) {
      localStorage.setItem('learning_tree_editor_referrer', this.$route.name)
      window.open(`/instructors/learning-trees/editor/${learningTreeId}`, '_blank')
    },
    instructorInNonBasicView () {
      return this.isInstructor() && !this.isInstructorWithAnonymousView && !this.presentationMode && this.questionView !== 'basic' && !this.inIFrame
    },
    studentShowPointsNonClicker () {
      return this.source === 'a' && !this.inIFrame && !this.isAnonymousUser && !this.isInstructorWithAnonymousView && !this.isInstructor() && this.user.role !== 5 && this.showPointsPerQuestion && this.assessmentType !== 'clicker' && !this.isFormative
    },
    studentNonClicker () {
      return this.source === 'a' && !this.inIFrame && !this.isAnonymousUser && !this.isInstructorWithAnonymousView && !this.isInstructor() && this.user.role !== 5 && this.assessmentType !== 'clicker'
    },
    getIframeTitle () {
      return `${this.title} - Question #${this.currentPage}`
    },
    fixLinks (iframeId) {
      $(`#${iframeId}`).contents().find('body').find('a').attr('target', '_blank')
    },
    getHintPenaltyMessage () {
      let message = ''

      if (this.questions[this.currentPage - 1].hint && this.hintPenalty) {
        message = `  In addition, a hint penalty of ${this.hintPenalty}% will be applied since the hint is viewable.`
      }
      return message
    },
    getMaximumNumberOfPointsPossible () {
      let numDeductionsToApply = parseFloat(this.questions[this.currentPage - 1].submission_count)
      this.hintPenalty = this.questions[this.currentPage - 1].shown_hint ? this.hintPenaltyIfShownHint : 0
      let totalPenalty = numDeductionsToApply * parseFloat(this.numberOfAllowedAttemptsPenalty) + this.hintPenalty
      return +Math.max(0, ((1 * this.questions[this.currentPage - 1].points) * (1 - totalPenalty / 100))).toFixed(4)
    },
    async handleShowSolution () {
      try {
        const { data } = await axios.post(`/api/solutions/show-solution/${this.assignmentId}/${this.questions[this.currentPage - 1].id}`)
        if (data.type !== 'success') {
          this.$noty.error(data.message)
          return false
        }
        await this.updateLastSubmittedAndLastResponse(this.assignmentId, this.questions[this.currentPage - 1].id)
        this.$bvModal.hide('modal-confirm-give-up')
      } catch (error) {
        this.$noty.error(error.message)
      }
    },
    getNumberOfRemainingAttempts () {
      const submissionCount = Number(this.questions[this.currentPage - 1].submission_count)
        ? parseInt(this.questions[this.currentPage - 1].submission_count)
        : 0
      const numberOfRemainingAttempts = parseInt(this.numberOfAllowedAttempts) - submissionCount
      const plural = +numberOfRemainingAttempts !== 1 ? 's' : ''
      return `${numberOfRemainingAttempts} attempt${plural} left`
    },
    setMyFavoritesFolder (myFavoritesFolder) {
      this.myFavoritesFolder = myFavoritesFolder
    },
    async filterByQuestionType (questionType) {
      this.filteringByQuestionType = true
      await this.getSelectedQuestions(this.assignmentId, this.questionId)
      let questions
      switch (questionType) {
        case ('auto-graded-only'):
          questions = this.questions.filter(question => question.technology !== 'text')
          questions.length ? this.questions = questions
            : this.$noty.info('This assignment does not have any auto-graded only assessments.')
          break
        case ('open-ended-only'):
          questions = this.questions.filter(question => question.technology === 'text')
          questions.length ? this.questions = questions
            : this.$noty.info('This assignment does not have any open-ended only assessments.')
          break
        case ('any-question-type'):
          break
      }
      this.filteringByQuestionType = false
    },
    async submitReasonForUploadingLocalSolution () {
      if (this.reasonForUploadingLocalSolution === 'libretexts_solution_error') {
        try {
          this.libretextsSolutionErrorForm.question_id = this.questions[this.currentPage - 1].id
          const { data } = await this.libretextsSolutionErrorForm.post(`/api/libretexts/solution-error`)
          if (data.type === 'success') {
            this.$noty.success(data.message)
          }
        } catch (error) {
          if (error.message.includes('status code 422')) {
            this.$nextTick(() => fixInvalid())
            this.allFormErrors = this.libretextsSolutionErrorForm.errors.flatten()
            this.$bvModal.show('modal-form-errors-libretexts-solution-error-form')
          }
          return false
        }
      }
      this.$bvModal.hide('modal-reason-for-uploading-local-solution')
      await this.openUploadFileModal(this.questions[this.currentPage - 1].id)
    },
    async initOpenUploadSolutionModal () {
      this.reasonForUploadingLocalSolution = 'prefer_own_solution'
      this.libretextsSolutionErrorForm.errors.clear()
      this.libretextsSolutionErrorForm.text = ''
      this.audioSolutionUploadUrl = `/api/solution-files/audio/${this.assignmentId}/${this.questions[this.currentPage - 1].id}`
      this.questions[this.currentPage - 1].solution_html
        ? this.$bvModal.show('modal-reason-for-uploading-local-solution')
        : await this.openUploadFileModal(this.questions[this.currentPage - 1].id)
    },
    handleFixCKEditor () {
      fixCKEditor(this)
    },
    async redirectIfBetaCourse () {
      try {
        const { data } = await axios.get(`/api/beta-assignments/get-from-alpha-assignment/${this.assignmentId}`)
        if (data.type !== 'success') {
          await this.$router.push({ name: 'beta_assignments_redirect_error' })
        }
        if (data.login_redirect) {
          await this.$router.push({ name: 'login' })
        } else if (data.beta_assignment_id) {
          this.assignmentId = data.beta_assignment_id
          console.log(`Beta assignment with id: ${this.assignmentId}`)
        }
      } catch (error) {
        await this.$router.push({ name: 'beta_assignments_redirect_error' })
      }
    },
    updateTotalScore () {
      let autoGradedScore = this.questions[this.currentPage - 1].submission_score
      let openEndedScore = this.questions[this.currentPage - 1].submission_file_score
      this.questions[this.currentPage - 1].total_score =
        ((autoGradedScore === 'N/A' || typeof autoGradedScore === 'undefined')
          ? 0
          : parseFloat(autoGradedScore)) +
        ((openEndedScore === 'N/A' || typeof openEndedScore === 'undefined')
          ? 0
          : parseFloat(openEndedScore))
    },
    async updateCompletionScoringMode (questionId) {
      try {
        const { data } = await this.completionScoringModeForm.patch(`/api/assignments/${this.assignmentId}/questions/${questionId}/update-completion-scoring-mode`)
        this.$noty[data.type](data.message)
        if (data.type === 'success') {
          this.questions[this.currentPage - 1].completion_scoring_mode = data.completion_scoring_mode
          if (data.update_completion_scoring_mode) {
            for (let i = 0; i < this.questions.length; i++) {
              this.questions[i].completion_scoring_mode = data.completion_scoring_mode
            }
          }
          this.setCompletionScoringModeMessage()
          this.$bvModal.hide('modal-update-completion-scoring-mode')
        }
      } catch (error) {
        if (!error.message.includes('status code 422')) {
          this.$noty.error(error.message)
        } else {
          this.$nextTick(() => fixInvalid())
          this.allFormErrors = this.completionScoringModeForm.errors.flatten()
          this.$bvModal.show('modal-form-errors-completion-scoring-mode')
        }
      }
    },
    openUpdateCompletionScoringModeModal () {
      if (this.isBetaAssignment) {
        this.$bvModal.show('modal-cannot-update-points-if-beta-assignment')
        return false
      }
      if (this.hasAtLeastOneSubmission) {
        this.$noty.info('Students have already made submission for this question so you can\'t change the scoring method.')
        return false
      }
      if (this.scoringType === 'c') {
        if (this.questions[this.currentPage - 1].completion_scoring_mode === '100% for either') {
          this.completionScoringModeForm.completion_scoring_mode = '100% for either'
          this.completionScoringModeForm.completion_split_auto_graded_percentage = '50'
          this.completionSplitOpenEndedPercentage = '50'
        } else {
          this.completionScoringModeForm.completion_scoring_mode = 'split'
          this.completionScoringModeForm.completion_split_auto_graded_percentage = this.questions[this.currentPage - 1].completion_scoring_mode.replace(/\D/g, '')
          this.completionSplitOpenEndedPercentage = 100 - parseInt(this.completionScoringModeForm.completion_split_auto_graded_percentage)
        }
      }
      this.$bvModal.show('modal-update-completion-scoring-mode')
    },
    setCompletionScoringModeMessage () {
      this.completionScoringModeMessage = ''
      let completionScoringMode = this.questions[this.currentPage - 1].completion_scoring_mode
      if (this.scoringType === 'c' && this.questions[this.currentPage - 1].has_auto_graded_and_open_ended) {
        let autoGradedPercent = parseFloat(completionScoringMode.replace(/\D/g, ''))
        let openEndedPercent = (100 - autoGradedPercent) / 100
        let autoGradedPoints = (autoGradedPercent / 100) * this.questions[this.currentPage - 1].points
        let openEndedPoints = openEndedPercent * this.questions[this.currentPage - 1].points
        if (completionScoringMode.includes('either')) {
          this.completionScoringModeMessage = this.isInstructor()
            ? 'Students receive full credit for either an auto-graded or open-ended submission.'
            : 'You will receive full credit for either an auto-graded or open-ended submission.<br>Note that your score will only be saved for the first submitted response.'
        } else {
          this.completionScoringModeMessage = this.isInstructor()
            ? 'Students'
            : 'You will'
          this.completionScoringModeMessage += ` receive ${autoGradedPoints} points for an auto-graded submission and ${openEndedPoints} points for an open-ended submission.`
        }
      }
    },
    resizeHandler () {
      this.zoomedOut = this.zoomGreaterThan(1.2)
      if (this.zoomedOut) {
        this.questionCol = 12
        this.bCardCols = 12
      } else {
        this.setQuestionCol()
        if (!this.clickerApp && !this.isPhone()) {
          this.bCardCols = 4
        }
      }
    },
    setQuestionCol () {
      this.questionCol = (this.isDiscussIt() || this.assessmentType === 'flashcard' || ['clicker', 'real time', 'delayed'].includes(this.assessmentType) ||
        !this.showSubmissionInformation) && (!this.caseStudyNotesByQuestion.length) ? 12 : 8
      if (this.clickerApp || this.isPhone()) {
        this.questionCol = 12
        this.bCardCols = 12
      }
    },
    async getMyFavoriteQuestions () {
      try {
        const { data } = await axios.get(`/api/my-favorites/assignment/${this.assignmentId}`)
        if (data.type === 'error') {
          this.$noty.error(data.message)
          return false
        }
        this.myFavoriteQuestionIds = []
        this.myFavoriteQuestions = data.my_favorite_questions
        console.info(this.myFavoriteQuestions)
        for (let i = 0; i < this.myFavoriteQuestions.length; i++) {
          this.myFavoriteQuestionIds.push(this.myFavoriteQuestions[i].my_favorites_question_id)
        }
        // console.log(this.myFavoriteQuestionIds)
      } catch (error) {
        this.$noty.error(error.message)
      }
    },
    async addQuestionToFavorites (type = 'single') {
      let questionIds = (type === 'all')
        ? this.questions.map(question => question.id)
        : [this.questions[this.currentPage - 1].id]
      if (this.myFavoritesFolder === null) {
        this.$noty.info('Please first choose a Favorites folder.')
        return false
      }
      try {
        const { data } = await axios.post('/api/my-favorites',
          {
            question_ids: questionIds,
            folder_id: this.myFavoritesFolder,
            chosen_assignment_ids: [this.assignmentId]
          })
        this.$noty[data.type](data.message)
        if (data.type === 'error') {
          return false
        }
        for (let i = 0; i < questionIds.length; i++) {
          this.myFavoriteQuestionIds.push(questionIds[i])
        }
        this.myFavoriteQuestions.push({
          my_favorites_folder_id: this.myFavoritesFolder,
          my_favorites_question_id: this.questions[this.currentPage - 1].id
        })
        this.$bvModal.hide(`modal-save-to-my-favorites`)
      } catch (error) {
        this.$noty.error(error.message)
      }
    },
    async removeMyFavoritesQuestion () {
      let questionId = this.questions[this.currentPage - 1].id
      // console.log(this.myFavoriteQuestions)
      let folderId = this.myFavoriteQuestions.find(myFavoriteQuestion => myFavoriteQuestion.my_favorites_question_id === questionId).my_favorites_folder_id
      // console.log(folderId)
      try {
        const { data } = await axios.delete(`/api/my-favorites/folder/${folderId}/question/${questionId}`)
        if (data.type === 'error') {
          this.$noty.error(data.message)
          return false
        } else {
          this.$noty.info(data.message)
        }
        this.myFavoriteQuestions = this.myFavoriteQuestions.filter(myFavoriteQuestion => myFavoriteQuestion.my_favorites_question_id !== questionId)
        // console.log(this.myFavoriteQuestions)
        this.myFavoriteQuestionIds = []
        for (let i = 0; i < this.myFavoriteQuestions.length; i++) {
          this.myFavoriteQuestionIds.push(this.myFavoriteQuestions[i].my_favorites_question_id)
        }
      } catch (error) {
        this.$noty.error(error.message)
      }
    },
    nextQuestion () {
      if (this.currentPage < this.questions.length) {
        this.currentPage++
        this.changePage(this.currentPage)
      }
    },
    showAttributionModal () {
      this.$bvModal.show('modal-attribution')
    },
    getThumbsUpWidth () {
      return this.inIFrame ? 150 : 275
    },
    viewInADAPT () {
      let link = window.location.href
      window.open(link)
    },
    async parentUpdateShownInIFrame (item, newValue) {
      try {
        const { data } = await axios.patch(`/api/assignments/${this.assignmentId}/questions/${this.questions[this.currentPage - 1].id}/iframe-properties`,
          { item: item })
        this.$noty[data.type](data.message)
        if (data.type === 'error') {
          return false
        }
        this.questions[this.currentPage - 1][`${item}_information_shown_in_iframe`] = newValue
        switch (item) {
          case ('submission'):
            this.submissionInformationShownInIFrame = newValue
            break
          case ('assignment'):
            this.assignmentInformationShownInIFrame = newValue
            break
          case ('attribution'):
            this.attributionInformationShownInIFrame = newValue
            break
        }
      } catch (error) {
        this.$noty.error(error.message)
        this.questions[this.currentPage - 1][`${item}_information_shown_in_iframe`] = !newValue
        switch (item) {
          case ('submission'):
            this.submissionInformationShownInIFrame = !newValue
            break
          case ('assignment'):
            this.assignmentInformationShownInIFrame = !newValue
            break
          case ('attribution'):
            this.attributionInformationShownInIFrame = !newValue
            break
        }
      }
    },
    refreshQuestionParent (message) {
      this.$noty.success(message)
    },
    async reloadSingleQuestion () {
      this.questionId = this.questions[this.currentPage - 1].id
      if (this.assessmentType === 'flashcard') {
        window.location.href = `/assignments/${this.assignmentId}/questions/view/${this.questionId}`
        return
      }
      await this.getSelectedQuestions(this.assignmentId, this.questionId)
      this.currentPage = this.getInitialCurrentPage(this.questionId)
      this.cacheIndex++
      await this.changePage(this.currentPage)
      this.reportCacheKey++
    },
    async reloadQuestionParent (questionId, message) {
      try {
        const { data } = await axios.get(`/api/questions/${questionId}`)
        if (data.type !== 'success') {
          this.$noty.error(data.message)
          return false
        }
        this.questions[this.currentPage - 1].non_technology_iframe_src = data.question.non_technology_iframe_src
        this.questions[this.currentPage - 1].non_technology = this.questions[this.currentPage - 1].non_technology_iframe_src !== ''
        this.questions[this.currentPage - 1].technology_iframe = data.question.technology_iframe_src
        this.questions[this.currentPage - 1].title = data.question.title
        this.questions[this.currentPage - 1].qti_json = data.question.qti_json
        this.qtiJson = data.question.qti_json
        this.questions[this.currentPage - 1].text_question = data.question.text_question
        this.questions[this.currentPage - 1].a11y_question = data.question.a11y_question
        this.questions[this.currentPage - 1].solution_html = data.question.solution_html
        this.questions[this.currentPage - 1].answer_html = data.question.answer_html
        this.questions[this.currentPage - 1].hint = data.question.hint
        this.questions[this.currentPage - 1].libretexts_link = data.question.libretexts_link
        this.questions[this.currentPage - 1].notes = data.question.notes
        this.$bvModal.hide('modal-question-has-submissions-in-this-assignment')
        this.cacheIndex++
        this.$forceUpdate()
        await this.$nextTick(() => {
          this.changePage(this.currentPage)
        })

        if (message) {
          this.$noty.success(message)
        }
        // console.log(this.cacheIndex)
      } catch (error) {
        this.$noty.error(error.message)
      }
    },
    async updateProperties () {
      this.propertiesForm.auto_attribution = this.autoAttribution
      try {
        const { data } = await this.propertiesForm.patch(`/api/questions/properties/${this.questions[this.currentPage - 1].id}`)
        this.$noty[data.type](data.message)
        if (data.type !== 'success') {
          return false
        }
        this.questions[this.currentPage - 1].auto_attribution = this.autoAttribution
        this.questions[this.currentPage - 1].attribution = this.autoAttribution ? null : this.propertiesForm.attribution
        this.questions[this.currentPage - 1].private_description = this.propertiesForm.private_description
        this.$bvModal.hide('modal-properties')
      } catch (error) {
        if (!error.message.includes('status code 422')) {
          this.$noty.error(error.message)
        }
      }
    },
    async openModalProperties () {
      this.currentUrl = this.getCurrentUrl()
      this.embedCode = this.getEmbedCode()
      this.libraryText = this.questions[this.currentPage - 1].library
      this.adaptId = `${this.assignmentId}-${this.questions[this.currentPage - 1].id}`
      this.pageId = this.questions[this.currentPage - 1].page_id
      this.assignmentInformationShownInIFrame = this.questions[this.currentPage - 1].assignment_information_shown_in_iframe
      this.submissionInformationShownInIFrame = this.questions[this.currentPage - 1].submission_information_shown_in_iframe
      this.attributionInformationShownInIFrame = this.questions[this.currentPage - 1].attribution_information_shown_in_iframe
      this.technology = this.questions[this.currentPage - 1].technology
      this.formattedTechnology = this.getFormattedTechnology(this.technology)
      this.technologySrc = this.getTechnologySrc('technology', 'technology_src', this.questions[this.currentPage - 1])
      this.a11yTechnologySrc = this.getTechnologySrc('a11y_technology', 'a11y_technology_src', this.questions[this.currentPage - 1])
      this.propertiesForm.attribution = this.questions[this.currentPage - 1].attribution
      this.propertiesForm.private_description = this.questions[this.currentPage - 1].private_description
      this.autoAttribution = !!this.questions[this.currentPage - 1].auto_attribution
      let vm = this
      this.updateAutoAttribution(vm, this.questions[this.currentPage - 1])
      this.$bvModal.show('modal-properties')
    },
    createQrCode () {
      qrCodeConfig.data = this.formativeQuestionURL ? this.formativeQuestionURL : this.currentUrl
      const qrCode = new QRCodeStyling(qrCodeConfig)
      qrCode.append(this.$refs['qrCodeCanvas'])
    },
    async submitRemoveSolution () {
      try {
        const { data } = await axios.delete(`/api/solution-files/${this.assignmentId}/${this.questions[this.currentPage - 1].id}`)
        if (data.type === 'success') {
          this.questions[this.currentPage - 1].solution = null
          if (this.questions[this.currentPage - 1].solution_html) {
            this.questions[this.currentPage - 1].solution_type = 'html'
          }
          this.$bvModal.hide('modal-remove-solution')
        }
        this.$noty[data.type](data.message)
      } catch (error) {
        this.$noty.error(error.message)
      }
    },
    hotKeys (event) {
      if (this.isFlashcard()) return
      let target = $(event.target)
      if (target.parents('div#question-to-view').length) {
        // don't want any right or left arrow while within the question context
        return
      }
      if (event.key === 'ArrowRight') {
        let modalElements = document.getElementsByClassName('modal-content')
        this.$bvModal.hide('modal-upload-file')
        if (!modalElements.length) {
          this.nextQuestion()
        }
      }
      if (event.key === 'ArrowLeft' && this.currentPage > 1) {
        let modalElements = document.getElementsByClassName('modal-content')
        this.$bvModal.hide('modal-upload-file')
        if (!modalElements.length) {
          this.currentPage--
          this.changePage(this.currentPage)
        }
      }
      if (this.isInstructor() && event.ctrlKey && event.key === 'e') {
        this.editQuestionSource(this.questions[this.currentPage - 1])
      }

      if ((event.key === 'Escape' && ('#modal-instructor-clicker-question___BV_modal_content_').length)) {
        this.$bvModal.hide('modal-instructor-clicker-question')
      }
      if (event.key === 'Escape' &&
        this.questionToEdit.id &&
        !$('#my-questions-question-to-view-questions-editor___BV_modal_content_').length &&
        !$('#modal-framework-aligner___BV_modal_content_').length) {
        // hack....just close if the preview isn't open.  For some reason, I couldn't get the edit modal to close
        this.$bvModal.hide(`modal-edit-question-${this.questionToEdit.id}`)
      }
    },
    async setPageAsSubmission (questionId) {
      try {
        // console.log(this.questionSubmissionPageForm)
        const { data } = await this.questionSubmissionPageForm.patch(`/api/submission-files/${this.assignmentId}/${questionId}/page`)
        this.$noty[data.type](data.message)
        this.$bvModal.hide('modal-upload-file')
        if (data.type === 'success') {
          this.questions[this.currentPage - 1].submission = data.submission
          this.questions[this.currentPage - 1].original_filename = data.original_filename
          this.questions[this.currentPage - 1].date_graded = 'N/A'
          this.questions[this.currentPage - 1].file_feedback = 'N/A'
          this.questions[this.currentPage - 1].submission_file_exists = true
          this.questions[this.currentPage - 1].late_file_submission = data.late_file_submission
          this.questions[this.currentPage - 1].submission_file_url = data.submission_file_url
          this.questions[this.currentPage - 1].date_submitted = data.date_submitted
        }
      } catch (error) {
        if (!error.message.includes('status code 422')) {
          this.$noty.error(error.message)
        }
      }
    },
    async toggleQuestionAttributes () {
      try {
        // console.log(this.questionView)
        const { data } = await axios.patch(`/api/cookie/set-question-view/${this.questionView}`)
        if (data.type === 'error') {
          return false
        }
        this.questionView = (this.questionView === 'basic') ? 'expanded' : 'basic'
      } catch (error) {
        this.$noty.error(error.message)
      }

      if (this.questionView === 'expanded') {
        this.typesetMath()
      }
    },
    onCKEditorNamespaceLoaded (CKEDITOR) {
      CKEDITOR.addCss('.cke_editable { font-size: 15px; }')
    },
    async submitResetDefaultOpenEndedText () {
      try {
        let questionId = this.questions[this.currentPage - 1].id
        const { data } = await axios.delete(`/api/submission-texts/${this.assignmentId}/${questionId}`)
        this.$noty[data.type](data.message)
        if (data.type === 'error') {
          return false
        }
        this.questions[this.currentPage - 1].date_submitted = data.date_submitted
        this.textSubmissionForm.text_submission = this.questions[this.currentPage - 1].open_ended_default_text
        this.questions[this.currentPage - 1].submission = null
      } catch (error) {
        if (!error.message.includes('status code 422')) {
          this.$noty.error(error.message)
        }
      }
      this.$bvModal.hide('modal-reset-to-default-text')
    },
    async submitDefaultOpenEndedText () {
      try {
        let questionId = this.questions[this.currentPage - 1].id
        const { data } = await this.openEndedDefaultTextForm.patch(`/api/assignments/${this.assignmentId}/questions/${questionId}/open-ended-default-text`)
        this.$noty[data.type](data.message)
        if (data.type === 'error') {
          return false
        }
        this.questions[this.currentPage - 1].open_ended_default_text = this.openEndedDefaultTextForm.open_ended_default_text
      } catch (error) {
        if (!error.message.includes('status code 422')) {
          this.$noty.error(error.message)
        }
      }
    },
    async viewClickerSubmissions (showAnswer) {
      try {
        const { data } = await axios.post(`/api/assignments/${this.assignmentId}/questions/${this.questions[this.currentPage - 1].id}/view-clicker-submissions/${+showAnswer}`)
        if (data.type === 'error') {
          this.$noty.error(data.message)
        }
      } catch (error) {
        this.$noty.error(error.message)
      }
    },
    async endClickerAssessment (message = '') {
      // START:  I think that if it's a student I'll need to do some other logic.  Why would it end it anyway?
      if (this.pollClosed) {
        return
      }
      this.pollClosed = true
      this.timeLeft = 0
      if (this.user.role === 2) {
        try {
          const { data } = await axios.post(`/api/assignments/${this.assignmentId}/questions/${this.questions[this.currentPage - 1].id}/end-clicker-assessment`,
            { message: message, solution_html: this.questions[this.currentPage - 1].solution_html })
          if (data.type === 'error') {
            this.$noty.error(data.message)
          } else {
            this.$bvModal.hide('modal-confirm-close-poll')
          }
        } catch (error) {
          this.$noty.error(error.message)
        }
      } else {
        if (this.clickerApp) {
          window.parent.postMessage(`{"source": "app_clicker","message": "Submissions are no longer accepted.","type":"info"}`, '*')
        }
      }
      this.clickerStatus = 'view_and_not_submit'
    },
    getTimeLeftMessage (props, assessmentType) {
      let message = ''
      if (assessmentType !== 'clicker') {
        message = '<span class="font-weight-bold">Due:</span> '
      }

      this.currentTimeLeft = props.totalMilliseconds
      const minutesPlural = props.minutes > 1 ? 's' : ''
      const secondsPlural = props.seconds > 1 || props.seconds === 0 ? 's' : ''
      const timeLeft = parseInt(this.timeLeft) / 1000
      if (timeLeft >= 60 * 60 * 24) {
        message += `${props.days} days, ${props.hours} hours,
          ${props.minutes} minutes, ${props.seconds} seconds`
      } else if (timeLeft >= 60 * 60) {
        message += `${props.hours}  hours,
          ${props.minutes}  minute${minutesPlural}, ${props.seconds} seconds`
      } else if (timeLeft > 60) {
        message += assessmentType !== 'clicker' ? `${props.minutes} minute${minutesPlural}, ${props.seconds} seconds`
          : `${props.minutes} min, ${props.seconds} sec`
      } else {
        message += `${props.seconds} second${secondsPlural}`
      }
      message += assessmentType === 'clicker' ? ` left` : '.'
      return message
    },
    async restartTimer () {
      try {
        const { data } = await this.clickerTimeForm.post(`/api/assignments/${this.assignmentId}/questions/${this.questions[this.currentPage - 1].id}/restart-timer`)
        if (data.type === 'error') {
          this.$noty.error(data.message)
        }
      } catch (error) {
        this.$noty.error(error.message)
      }
    },
    async resetClickerAssessment (reloadStudentView) {
      try {
        const { data } = await axios.delete(`/api/submissions/assignments/${this.assignmentId}/questions/${this.questions[this.currentPage - 1].id}/delete-by-assignment-and-question`)
        if (data.type === 'error') {
          this.$noty[data.type](data.message)
          return false
        } else {
          this.clickerTimeForm.reload_student_view = reloadStudentView
          const clickerAssessmentStarted = await this.startClickerAssessment()
          if (clickerAssessmentStarted) {
            this.$noty.info('')
          }
        }
      } catch (error) {
        if (!error.message.includes('status code 422')) {
          this.$noty.error(error.message)
        }
        this.clickerTimeForm.reload_student_view = false
      }
    },
    async startClickerAssessment () {
      try {
        const { data } = await this.clickerTimeForm.post(`/api/assignments/${this.assignmentId}/questions/${this.questions[this.currentPage - 1].id}/start-clicker-assessment`)
        this.clickerTimeForm.restart = false
        if (data.type === 'error') {
          this.$noty[data.type](data.message)
          return false
        }
      } catch (error) {
        this.clickerTimeForm.restart = false
        if (!error.message.includes('status code 422')) {
          this.$noty.error(error.message)
          return false
        }
      }
      return true
    },
    openShowAudioSolutionModal (event) {
      event.preventDefault()
      this.$bvModal.show('modal-show-audio-solution')
    },
    closeAudioSolutionModal () {
      this.solutionTextForm.solution_text = ''
      this.solutionTextForm.errors.clear()
      this.$bvModal.hide('modal-upload-file')
      this.$refs.recorder.removeRecord()
    },
    async submitSolutionText () {
      try {
        let questionId = this.questions[this.currentPage - 1].id
        this.solutionTextForm.question_id = questionId
        const { data } = await this.solutionTextForm.post(`/api/solutions/text/${this.assignmentId}/${questionId}`)
        this.$noty[data.type](data.message)
        if (data.type === 'error') {
          return false
        }

        this.questions[this.currentPage - 1].solution_text = this.solutionTextForm.solution_text
        this.savedText = this.savedText + 1

        this.$refs.recorder.removeRecord()
        this.$bvModal.hide('modal-upload-file')
      } catch (error) {
        if (!error.message.includes('status code 422')) {
          this.$noty.error(error.message)
        }
      }
    },
    updateIsLoadingPieChart () {
      this.isLoadingPieChart = false
    },
    submittedAudioSolutionUpload (response) {
      let data = response.data
      this.uploadedAudioSolutionDataType = (data.type === 'success') ? 'font-weight-bold text-success' : 'font-weight-bold text-danger'
      this.uploadedAudioSolutionDataMessage = data.message
      this.showUploadedAudioSolutionMessage = true
      setTimeout(() => {
        this.showUploadedAudioSolutionMessage = false
      }, 3000)
      if (data.type === 'success') {
        this.questions[this.currentPage - 1].solution = data.solution
        this.questions[this.currentPage - 1].solution_type = 'audio'
        this.questions[this.currentPage - 1].solution_file_url = data.solution_file_url
        this.showSuccessfulAudioSubmissionMessage = true
        this.showAddTextToSupportTheAudioFile = true
      } else {
        this.$refs.recorder.removeRecord()
        this.$bvModal.hide('modal-upload-file')
        this.$noty.error(data.message)
      }
    },
    submittedAudioUpload (response) {
      let data = response.data
      console.error(data)
      this.openEndedSubmissionDataType = (data.type === 'success') ? 'success' : 'danger'
      this.submissionDataMessage = data.message
      if (this.openEndedSubmissionDataType !== 'success') {
        this.$bvModal.show('modal-thumbs-down')
      } else {
        this.cacheKey++
        this.$bvModal.show('modal-submission-accepted')
        this.completedAllAssignmentQuestions = data.completed_all_assignment_questions && this.user.role === 3
      }

      if (data.type === 'success') {
        this.questions[this.currentPage - 1].date_submitted = data.date_submitted
        this.questions[this.currentPage - 1].submission_file_url = data.submission_file_url
        this.questions[this.currentPage - 1].late_file_submission = data.late_file_submission
        this.questions[this.currentPage - 1].submission_file_exists = true
        this.questions[this.currentPage - 1].submission_file_score = data.score
        this.updateTotalScore()
      }
      if (!this.isPhone()) {
        this.$refs.uploadRecorder.removeRecord()
      }
      this.$bvModal.hide('modal-upload-file')
    },
    failedAudioUpload (data) {
      this.$noty.error('We were not able to perform the upload.  Please try again or contact us for assistance.')
      axios.post('/api/submission-audios/error', JSON.stringify(data))
      this.$refs.recorder.removeRecord()
    },
    updateShare () {
      this.currentUrl = this.getCurrentUrl()
      this.embedCode = this.getEmbedCode()
    },
    getFormattedTechnology (technology) {
      let formattedTechnology
      switch (technology) {
        case ('h5p'):
          formattedTechnology = 'H5P'
          break
        case ('webwork'):
          formattedTechnology = 'WebWork'
          break
        case ('imathas'):
          formattedTechnology = 'IMathAS'
          break
        default:
          formattedTechnology = technology
      }
      return formattedTechnology
    },
    getEmbedCode () {
      return `<iframe id="adapt-${this.assignmentId}-${this.questions[this.currentPage - 1].id}" allowtransparency="true" frameborder="0" scrolling="no" src="${this.currentUrl}" style="width: 1px;min-width: 100%;min-height: 100px;" />`
    },
    getCurrentUrl () {
      return `${window.location.origin}/assignments/${this.assignmentId}/questions/view/${this.questions[this.currentPage - 1].id}`
    },
    getInitialCurrentPage (questionId) {
      for (let i = 1; i <= this.questions.length; i++) {
        if (parseInt(this.questions[i - 1].id) === parseInt(questionId)) {
          return i
        }
      }
    },
    capitalize (word) {
      return word ? word.charAt(0).toUpperCase() + word.slice(1) : ''
    },
    getOpenEndedTitle () {
      let capitalizedTitle = ''
      if (+this.openEndedSubmissionType !== 0) {
        let openEndedSubmissionType = this.openEndedSubmissionType.includes('text') ? 'text' : this.openEndedSubmissionType
        capitalizedTitle = this.capitalize(openEndedSubmissionType)
      }
      return `${capitalizedTitle} Submission Information`
    },
    async submitText (showModal = true) {
      try {
        this.textSubmissionForm.questionId = this.questions[this.currentPage - 1].id
        this.textSubmissionForm.assignmentId = this.assignmentId
        this.textSubmissionForm.pasted_content = this.pastedContent
        console.error(this.textSubmissionForm)
        const { data } = await this.textSubmissionForm.post('/api/submission-texts')
        this.submissionDataMessage = data.message
        if (data.type === 'success') {
          this.questions[this.currentPage - 1].date_submitted = data.date_submitted
          if (data.date_submitted) {
            this.lastAutoSavedAt = new Date(data.date_submitted).toLocaleTimeString([], {
              hour: '2-digit',
              minute: '2-digit'
            })
          }
          this.questions[this.currentPage - 1].submission = this.textSubmissionForm.text_submission
          this.questions[this.currentPage - 1].submission_file_score = data.score
          this.updateTotalScore()
          this.cacheKey++
          if (showModal) {
            this.$bvModal.show('modal-submission-accepted')
          }
          this.completedAllAssignmentQuestions = data.completed_all_assignment_questions && this.user.role === 3
        } else {
          this.$bvModal.show('modal-thumbs-down')
        }
      } catch (error) {
        this.$noty.error(error.message)
      }
    },
    updateClickerMessage (clickerStatus, message = '') {
      switch (clickerStatus) {
        case ('paused'):
          this.clickerPaused = true
          break
        case ('resumed'):
          this.timeLeft = this.currentTimeLeft
          this.$nextTick(() => {
            this.clickerPaused = false
          })
          break
        case ('show_open'):
          this.clickerMessage = 'This question is ready to be opened.'
          this.clickerMessageType = 'info'
          break
        case ('view_and_submit'):
          this.clickerPaused = false
          if (this.user.role === 2) {
            this.openingClicker = false
            this.modalInstructorClickerQuestionShown = true
            this.pollClosed = false
            this.$bvModal.show('modal-instructor-clicker-question')
          }
          break
        case ('view_and_not_submit'):
          if (this.user.role === 3) {
            this.clickerMessage = message || ''
          }
          break
        case ('neither_view_nor_submit'):
          this.clickerMessage = this.user.role === 2
            ? 'This question is not yet open.'
            : `Please wait for your instructor to open Question #${this.currentPage} for submission.`
          this.clickerMessageType = 'info'
      }
      this.clickerStatus = clickerStatus
      console.log('New clicker status: ' + this.clickerStatus)
    },
    async getClickerSubmissions (questionId) {
      try {
        const { data } = await axios.patch(`/api/submissions/${this.assignmentId}/questions/${questionId}/submission-chart-data`)

        if (data.type !== 'error') {
          // window.location = `/assignments/${this.assignmentId}/questions/view/${data.redirect_question}`
          // console.log(data)
          this.responsePercent = data.response_percent
          if (data.default_submission_results) {
            this.defaultSubmissionResults = data.default_submission_results
            if (data.answer_shown) {
              this.clickerAnswerShown = true
            }
          } else {
            this.piechartdata = data.pie_chart_data
            this.correctAnswerIndex = data.correct_answer_index
          }
          this.canViewClickerSubmissions = true
          this.$nextTick(() => {
            this.chartUpdateKey++
          })
        } else {
          this.$noty.error(data.message)
        }
      } catch (error) {
        this.$noty.error(error.message)
      }

      // this.chartdata = await this.getScoresSummary(this.assignmentId, `/api/scores/summary/${this.assignmentId}/${questionId}`)
    },
    async initUpdateOpenEndedSubmissionType (questionId) {
      try {
        const { data } = await axios.get(`/api/assignments/${this.assignmentId}/questions/${questionId}/has-non-scored-submission-files`)
        if (data.type === 'success') {
          if (data.has_non_scored_submission_files) {
            this.confirmDeleteOpenEndedSubmissionsMessage = data.message
            this.$bvModal.show('modal-confirm-delete-open-ended-submissions')
          } else {
            await this.updateOpenEndedSubmissionType(questionId)
          }
        } else {
          this.openEndedSubmissionType = this.originalOpenEndedSubmissionType
        }
      } catch (error) {
        this.$noty.error(error.message)
        this.openEndedSubmissionType = this.originalOpenEndedSubmissionType
      }
    },
    async updateOpenEndedSubmissionType (questionId) {
      try {
        const { data } = await axios.patch(`/api/assignments/${this.assignmentId}/questions/${questionId}/update-open-ended-submission-type`, { 'open_ended_submission_type': this.openEndedSubmissionType })
        this.$noty[data.type](data.message)
        if (data.type === 'success') {
          this.questions[this.currentPage - 1].open_ended_submission_type = this.openEndedSubmissionType
        } else {
          this.openEndedSubmissionType = this.originalOpenEndedSubmissionType
        }
      } catch (error) {
        this.$noty.error(error.message)
        this.openEndedSubmissionType = this.originalOpenEndedSubmissionType
      }
    },
    getWindowLocation () {
      return window.location
    },
    openShowAssignmentStatisticsModal () {
      this.showAssignmentStatisticsModal = true
    },
    async getEmailToGraderSubject () {
      let courseName
      try {
        const { data } = await axios.get(`/api/assignments/${this.assignmentId}/get-course-name-from-assignment`)
        if (data.type === 'success') {
          courseName = data.course_name + ' --- '
        }
      } catch (error) {
      }
      this.emailToGraderSubject = `${courseName} ${this.name}, Question #${this.currentPage}`
    },

    async openContactGraderModal (type = 'open-ended') {
      const { data } = await axios.get(`/api/contact-grader-overrides/${this.assignmentId}`)
      if (data.type === 'error') {
        this.$noty.error(data.message)
        return false
      }
      let graderId = this.questions[this.currentPage - 1].grader_id
      let contactGraderOverrideId = data.contact_grader_override_id
      let defaultGraderId = data.default_grader_id

      if (['auto-graded', 'discuss-it'].includes(type)) {
        graderId = contactGraderOverrideId || defaultGraderId
      } else if (contactGraderOverrideId) {
        graderId = contactGraderOverrideId
      }
      this.$refs.email.setExtraParams({
        'assignment_id': this.assignmentId,
        'question_id': this.questions[this.currentPage - 1].id
      })
      this.$refs.email.openSendEmailModal(graderId)
    },
    getModalUploadFileTitle () {
      let solutionType = this.solutionTypeIsPdfImage ? 'PDF/Image' : 'Audio'
      return this.user.role === 3 ? 'Upload Open-ended Submission (' + this.getSolutionUploadTypes() + ')' : `Upload ${solutionType} Solution`
    },
    getSolutionUploadTypes () {
      if (this.uploadLevel === 'question') {
        return this.isOpenEndedAudioSubmission ? getAcceptedFileTypes('.mp3') : getAcceptedFileTypes()
      } else {
        return getAcceptedFileTypes('.pdf')
      }
    },
    async updateLastSubmittedAndLastResponse (assignmentId, questionId) {
      try {
        const { data } = await axios.get(`/api/assignments/${assignmentId}/${questionId}/last-submitted-info`)
        let info = ['last_submitted',
          'student_response',
          'answered_correctly',
          'submission_count',
          'submission_score',
          'problem_jwt',
          'late_penalty_percent',
          'answered_correctly_at_least_once',
          'late_question_submission',
          'qti_answer_json',
          'session_jwt',
          'qti_json',
          'solution',
          'solution_file_url',
          'solution_text',
          'solution_type',
          'imathas_solution',
          'answer_html',
          'solution_html',
          'submission_array'
        ]
        console.log(data['submission_array'])
        for (let i = 0; i < info.length; i++) {
          this.questions[this.currentPage - 1][info[i]] = data[info[i]]
        }
        if (this.questions[this.currentPage - 1]['technology'] === 'webwork') {
          if (data.technology_iframe_src) {
            // need to re-load the question potentially for alogrithmic solutions
            this.questions[this.currentPage - 1].technology_iframe = data.technology_iframe_src
            let vm = this
            await this.getTechnologySrcDoc(vm, data.technology_iframe_src, this.assignmentId, this.questionId, 'submissions')
            this.cacheIndex++
          }
          this.addGlow(this.event, data['submission_array'], this.questions[this.currentPage - 1]['technology'])
        }
        this.cacheKey++
        if (this.questions[this.currentPage - 1]['technology'] === 'imathas') {
          this.questions[this.currentPage - 1].technology_iframe = data.technology_iframe_src
          this.showSolutionFileHTML = this.questions[this.currentPage - 1].imathas_solution
        }
        if (['webwork', 'imathas'].includes(this.questions[this.currentPage - 1]['technology'])) {
          this.submissionArray = data['submission_array']
        }

        this.qtiJson = this.questions[this.currentPage - 1]['qti_json']
        this.$forceUpdate()
        console.log(data.too_many_submissions)
        this.submitButtonActive = !data.too_many_submissions
        if (['real time', 'learning tree'].includes(this.assessmentType)) {
          this.numberOfRemainingAttempts = this.getNumberOfRemainingAttempts()
          this.maximumNumberOfPointsPossible = this.getMaximumNumberOfPointsPossible()
          if (this.questions[this.currentPage - 1].solution || this.questions[this.currentPage - 1].solution_html) {
            this.showSolutionFileHTML = true
          }
        }
        if (this.assessmentType === 'clicker') {
          this.numberOfRemainingAttempts = this.getNumberOfRemainingAttempts()
        }
        this.updateTotalScore()
        await this.updateTimeOnTask(assignmentId, questionId)

        // show initially if you made no attempts OR you've already visited the learning tree
        // if you made an attempt, hide the question until you visit the learning tree
        // only get additional points and with a penalty IF they get it all correct
        if (this.user.role === 3) {
          await this.canSubmit()
        }
        this.submitWorkKey++
        await this.$nextTick()
        await this.typesetMath()
      } catch (error) {
        this.$noty.error(error.message)
      }
    },
    async updateTimeOnTask (assignmentId, questionId) {
      let submitTimeLessStartTime = performance.now() - this.taskStartTime
      submitTimeLessStartTime /= 1000
      submitTimeLessStartTime = Math.round(submitTimeLessStartTime)
      console.log(`Submit less start time: ${submitTimeLessStartTime}`)
      console.log(`Time inactive: ${this.totalTimeInTaskInactive}`)
      try {
        await axios.patch(`/api/assignment-question-time-on-tasks/assignment/${assignmentId}/question/${questionId}`, {
          time_on_task: submitTimeLessStartTime - this.totalTimeInTaskInactive > 0 ? submitTimeLessStartTime - this.totalTimeInTaskInactive : submitTimeLessStartTime,
          submit_time_less_start_time: submitTimeLessStartTime,
          total_time_in_task_inactive: this.totalTimeInTaskInactive
        })
      } catch (error) {
        console.log(error.message)
      }
      this.startTimeTaskInactive = 0
    },
    isInstructor () {
      return (this.user.role === 2)
    },
    hideResponse () {
      this.showSubmissionMessage = false
    },
    async showResponse (data) {
      const success = data && data.type === 'success'
      if (this.assessmentType === 'flashcard') {
        try {
          if (success) {
            this.questions.find(q => q.id === data.question_id).total_score = data.score
            this.questions.find(q => q.id === data.question_id).last_submitted = data.last_submitted
          }
          this.$refs.qtiViewer.$refs.flashcardViewer.onSubmitResult(success)
        } catch (e) { /* viewer may not be mounted */
          console.error(e)
        }
        return
      }

      console.log(data)
      this.cacheKey++
      this.questions[this.currentPage - 1].submissions_array = []
      this.submissionDataType = ['success', 'info'].includes(data.type) ? data.type : 'danger'
      console.error(data)
      if (data.type === 'preview') {
        return
      }
      if (data.type === 'unconfirmed') {
        this.user.role === 3 ? await this.initConfirmSubmission() : await this.completeSubmission()
        return
      }
      this.submissionDataMessage = data.message
      this.learningTreeMessage = data.learning_tree_message
      this.showSubmissionMessage = true
      if (this.submissionDataType !== 'danger') {
        if (data.not_updated_message) {
          this.$bvModal.show('modal-not-updated')
        } else {
          if (this.ish5pActivitySet) {
            await this.getH5pVideoInteractionSubmissions()
            this.completedAllAssignmentQuestions = false
            this.$bvModal.show('modal-submission-accepted')
          } else {
            if (!this.isFormative) {
              if (this.clickerApp) {
                let submissionDataMessage = data.message.replace('"', '\'\'')
                let type = data.type
                window.parent.postMessage(`{"source": "app_clicker","message": "${submissionDataMessage}","type":"${type}"}`, '*')
              } else if (this.assessmentType !== 'flashcard') {
                this.showSubmitMoleculeSubmission = false
                this.completedAllAssignmentQuestions = data.completed_all_assignment_questions && this.user.role === 3
                this.isFlashcard() ? this.nextQuestion() : this.$bvModal.show('modal-submission-accepted')
              }
            }
          }
        }
        if (this.assessmentType !== 'flashcard') {
          await this.updateLastSubmittedAndLastResponse(this.assignmentId, this.questions[this.currentPage - 1].id)
        }
        this.showSubmitMoleculeSubmission = true
        this.renderMathJax()
      } else {
        let questionType
        try {
          const qtiJson = JSON.parse(this.questions[this.currentPage - 1].qti_json)
          questionType = qtiJson.questionType
        } catch (error) {
          questionType = ''
        }
        if (questionType === 'multiple_answers') {
          this.revertToOriginal('multiple_answers')
        }
        if (this.clickerApp) {
          let submissionDataMessage = data.message.replace('"', '\'\'')
          window.parent.postMessage(`{"source": "app_clicker","message": "${submissionDataMessage}","type":"error"}`, '*')
        } else {
          this.$bvModal.show('modal-thumbs-down')
        }
      }
    },
    async updatePoints (questionId) {
      if (this.isBetaAssignment) {
        this.$bvModal.show('modal-cannot-update-points-if-beta-assignment')
        return false
      }
      try {
        const { data } = await this.questionPointsForm.patch(`/api/assignments/${this.assignmentId}/questions/${questionId}/update-points`)
        this.$noty[data.type](data.message)
        if (data.type === 'success') {
          this.questions[this.currentPage - 1].points = this.questionPointsForm.points
          if (data.update_points) {
            for (let i = 0; i < this.questions.length; i++) {
              this.questions[i].points = this.questionPointsForm.points
            }
          }
          this.setCompletionScoringModeMessage()
          this.enteredPoints = false
          this.reportCacheKey++
        }
      } catch (error) {
        if (!error.message.includes('status code 422')) {
          this.$noty.error(error.message)
        }
      }
    },
    updatePointsBasedOnNewWeights (data) {
      for (let i = 0; i < data.updated_points.length; i++) {
        let updatedQuestion = data.updated_points[i]
        this.questions.find(question => parseInt(question.id) === parseInt(updatedQuestion.question_id)).points = updatedQuestion.points
      }
    },
    async updateWeight (questionId) {
      if (this.isBetaAssignment) {
        this.$bvModal.show('modal-cannot-update-points-if-beta-assignment')
        return false
      }
      try {
        const { data } = await this.questionWeightForm.patch(`/api/assignments/${this.assignmentId}/questions/${questionId}/update-weight`)
        this.$noty[data.type](data.message)
        if (data.type === 'success') {
          this.questions[this.currentPage - 1].points = data.points
          this.questions[this.currentPage - 1].weight = this.questionWeightForm.weight
          this.updatePointsBasedOnNewWeights(data)
          this.setCompletionScoringModeMessage()
          this.enteredPoints = false
        }
      } catch (error) {
        error.message.includes('status code 422')
          ? this.$noty.error(this.questionWeightForm.errors.get('weight'))
          : this.$noty.error(error.message)
      }
    },
    async openUploadFileModal (questionId) {
      if (this.uploadFileType === 'submission') {
        this.uploadLevel = 'question'
        try {
          const { data } = await axios.post('/api/submission-files/can-submit-file-submission', {
            assignmentId: this.assignmentId,
            questionId: this.questions[this.currentPage - 1].id
          })
          if (data.type === 'error') {
            this.$noty.error(data.message)
            return false
          }
        } catch (error) {
          this.$noty.error(error.message)
          return false
        }
      }
      this.$bvModal.show('modal-upload-file')
      this.$nextTick(() => {
        makeFileUploaderAccessible()
      })
      this.questionSubmissionPageForm.errors.clear()
      this.questionSubmissionPageForm.page = ''
      this.uploadFileForm.errors.clear(this.uploadFileType)
      this.uploadFileForm.questionId = questionId
      this.uploadFileForm.assignmentId = this.assignmentId
      this.cutupsForm.chosen_cutups = ''
      this.cutupsForm.question_num = this.currentPage
      this.currentCutup = 1
    },
    async handleOK () {
      this.uploadFileForm.errors.clear(this.uploadFileType)
      this.uploadFileForm.uploadLevel = this.uploadLevel
      this.uploadFileForm.s3_key = this.s3Key
      this.uploadFileForm.original_filename = this.originalFilename
      // Prevent modal from closing
      // Trigger submit handler
      if (this.uploading) {
        this.$noty.info('Please be patient while the file is uploading.')
        return false
      }
      this.processingFile = true

      try {
        await this.submitUploadFile(this.uploadFileType, this.uploadFileForm, this.$noty, this.$nextTick, this.$bvModal, this.questions[this.currentPage - 1], this.uploadFileUrl, false)
        if (this.user.role === 3) {
          this.updateTotalScore()
        }
      } catch (error) {
        this.$noty.error(error.message)
      }

      if (!this.uploadFileForm.errors.has(this.uploadFileType)) {
        this.user.role === 2 ? await this.getCutups(this.assignmentId)
          : this.showCurrentFullPDF = true
      }

      if (!this.uploadFileForm.errors.any() &&
        (this.uploadLevel === 'question' || !this.showCurrentFullPDF)) {
        if (!this.questions[this.currentPage - 1].can_give_up) {
          this.questions[this.currentPage - 1].solution_type = 'q'
        }
        this.$bvModal.hide(`modal-upload-file`)
      }

      this.processingFile = false
      this.files = []
    },
    handleCancel () {
      if (this.$refs.upload) {
        this.$refs.upload.active = false
      }
      this.files = []
      this.processingFile = false
      this.$bvModal.hide(`modal-upload-file`)
    },
    showIframe () {
      this.iframeLoaded = true
    },
    getTitle (currentPage) {
      if (!this.questions[currentPage - 1]) return ''
      if (this.assessmentType === 'flashcard' && this.user.role !== 2) return ''
      return this.questions[currentPage - 1].title || `Question #${currentPage - 1}`
    },
    getQtiJson () {
      let qtiJson
      const currentQuestion = this.questions[this.currentPage - 1]

      // For forge_iteration questions, get qti_json from the parent forge question
      if (currentQuestion.qti_json_type === 'forge_iteration' && currentQuestion.forge_source_id) {
        const parentQuestion = this.questions.find(q => q.id === currentQuestion.forge_source_id)
        if (parentQuestion && parentQuestion.qti_json) {
          // Use parent's qti_json but change questionType to forge_iteration
          try {
            const parentQtiJson = JSON.parse(parentQuestion.qti_json)
            parentQtiJson.questionType = 'forge_iteration'
            qtiJson = JSON.stringify(parentQtiJson)
          } catch (error) {
            console.log('Error parsing parent qti_json:', error)
            qtiJson = JSON.stringify({
              questionType: 'forge_iteration',
              prompt: '<p class="text-danger">Error: Could not parse the original Forge prompt.</p>'
            })
          }
        } else {
          // Parent question not found or has no qti_json
          qtiJson = JSON.stringify({
            questionType: 'forge_iteration',
            prompt: '<p class="text-danger">Error: Could not find the original Forge prompt.</p>'
          })
        }
      } else {
        qtiJson = currentQuestion.qti_json
      }

      try {
        const parsedQtiJson = JSON.parse(qtiJson)
        if (this.presentationMode) {
          switch (parsedQtiJson.questionType) {
            case ('multiple_choice'):
              parsedQtiJson.feedback = {}
              qtiJson = JSON.stringify(parsedQtiJson)
              break
            default:
              break
          }
        }
      } catch (error) {
        console.log(error)
        // do nothing and keep the original
      }

      return { 'qtiJson': qtiJson, 'submitButtonActive': this.submitButtonActive }
    },
    getA11yQtiJson () {
      return {
        'qtiJson': this.questions[this.currentPage - 1].a11y_qti_json,
        'submitButtonActive': this.submitButtonActive
      }
    },
    reloadByQuestionNumber (newPageNumber) {
      window.location = `/assignments/${this.assignmentId}/questions/view/${this.questions[newPageNumber - 1].id}`
    },
    async changePage (currentPage) {
      this.attachments = []
      this.questionStatus = ''
      this.showSolutionFileHTML = false
      if (this.latePolicy === 'marked late' && this.timeLeft === 0) {
        this.questionStatus = 'late'
      }
      this.extraWaitForRenderComplete = !this.isPhone()
      this.enteredPoints = false
      this.showQtiJsonQuestionViewer = false
      this.submitButtonActive = true
      if (!this.questions[currentPage - 1]) {
        this.$noty.error('No question exists; you may be trying to reload a question that you have removed from the assignment.')
        this.isLoading = false
        return false
      }

      if (this.isFormative) {
        this.formativeQuestionURL = window.location.origin + `/students/assignments/${this.assignmentId}/init-formative/${this.questions[currentPage - 1].id}`
      }

      this.qtiJson = this.questions[this.currentPage - 1].qti_json

      this.iframeDomLoaded = false
      this.submitButtonsDisabled = false
      console.log('webwork stuff')
      this.technologySrcDoc = ''

      await this.$nextTick(() => {
          this.submitWorkKey++
          this.showSolutionFileHTML = this.questions[this.currentPage - 1].solution ||
            this.questions[this.currentPage - 1].solution_html ||
            this.questions[this.currentPage - 1].imathas_solution
          switch (this.questions[this.currentPage - 1].technology) {
            case ('webwork'):
              let href = new URL(this.questions[this.currentPage - 1].technology_iframe)
              if (this.questions[this.currentPage - 1].session_jwt) {
                console.log(`New session JWT: ${this.questions[this.currentPage - 1].session_jwt}`)
                href.searchParams.set('sessionJWT', this.questions[this.currentPage - 1].session_jwt)
              }
              let vm = this
              this.getTechnologySrcDoc(vm, href.toString(), this.assignmentId, this.questions[this.currentPage - 1].id, 'submissions')
              if (this.questions[this.currentPage - 1].render_webwork_solution) {

                if (this.user.role === 2) {
                  const url = new URL(this.questions[this.currentPage - 1].technology_iframe)
                  const problemJWT = url.searchParams.get('problemJWT')
                  this.getWebworkSolution(problemJWT)
                }
              }
              break
          }
          this.submissionArray = this.questions[this.currentPage - 1]['submission_array']
        }
      )
      this.attachments = this.questions[this.currentPage - 1].attachments ? JSON.parse(this.questions[this.currentPage - 1].attachments) : []

      if (this.user.role === 3) {
        if (this.pastDue) {
          await this.initReviewQuestionTimeSpent()
        } else {
          this.taskStartTime = performance.now()
          this.totalTimeInTaskInactive = 0
        }
      }
      this.totalTimeInTaskInactive = 0
      this.startTimeTaskInactive = 0
      this.maxScore = null // used for H5P video interaction questions
      this.showSolutionTextForm = false
      this.showAddTextToSupportTheAudioFile = false
      this.$nextTick(() => {
        if (['real time', 'learning tree'].includes(this.assessmentType)) {
          this.numberOfRemainingAttempts = this.getNumberOfRemainingAttempts()
          this.maximumNumberOfPointsPossible = this.getMaximumNumberOfPointsPossible()
        }
      })

      await this.getCaseStudyNotesByQuestion()

      if (this.assessmentType === 'clicker') {
        this.justLoadedPage = true
        this.selectedClickerViewOption = 'question'
        this.clickerStatus = this.questions[currentPage - 1].clicker_status
        this.clickerTimeForm.time_to_submit = this.defaultClickerTimeToSubmit
        this.timeLeft = this.questions[this.currentPage - 1].clicker_time_left

        await this.getClickerSubmissions(this.questions[this.currentPage - 1].id)
        this.canViewClickerSubmissions = true
        this.clickerViewIsSubmissions = true
        if (this.user.role === 3) {
          if (this.questions[this.currentPage - 1].clicker_status === 'view_and_not_submit') {
            this.clickerViewOptions = [
              { text: 'Question', value: 'question' },
              { text: 'Submissions', value: 'submissions' },
              { text: 'Scores', value: 'scores' }
            ]
            const showSolutionRadioButtonSolution = this.questions[this.currentPage - 1].solution_html || this.questions[this.currentPage - 1].qti_answer_json
            if (!showSolutionRadioButtonSolution) {
              this.clickerViewOptions = this.clickerViewOptions.filter(item => item.value !== 'solution')
            } else {
              if (!this.clickerViewOptions.find(item => item.value === 'solution')) {
                this.clickerViewOptions.push({ text: 'Solution', value: 'solution' })
              }
            }
          } else {
            this.clickerViewOptions = []
          }
        }
        if (this.user.role === 2) {
          if (this.questions[this.currentPage - 1].time_to_submit) {
            this.clickerTimeForm.time_to_submit = this.questions[this.currentPage - 1].time_to_submit
          }
        }
      }
      if (this.assessmentType === 'learning tree') {
        const xCenter = window.innerWidth / 2
        this.learningTreeSrc = this.user.role === 3
          ? `/students/learning-trees/${this.assignmentId}/${this.questions[currentPage - 1].learning_tree_id}/${this.questions[this.currentPage - 1].id}/${xCenter}`
          : `/instructors/learning-trees/editor/${this.questions[currentPage - 1].learning_tree_id}/0/${xCenter}`
      }
      this.showOpenEndedSubmissionMessage = false
      this.solutionTextForm.solution_text = this.questions[currentPage - 1].solution_text
      this.audioUploadUrl = `/api/submission-audios/${this.assignmentId}/${this.questions[currentPage - 1].id}/0`
      this.showQuestion = true
      this.openEndedSubmissionType = this.questions[currentPage - 1].open_ended_submission_type

      this.ish5pActivitySet = ['Interactive Video', 'Question Set'].includes(this.questions[currentPage - 1].h5p_type)
      this.isOpenEndedAudioSubmission = (this.openEndedSubmissionType === 'audio')
      this.showAudioUploadComponent = this.isOpenEndedAudioSubmission
      this.isOpenEndedFileSubmission = (this.openEndedSubmissionType === 'file')
      if (this.ish5pActivitySet) {
        await this.getH5pVideoInteractionSubmissions()
      }
      this.setCompletionScoringModeMessage()
      this.isOpenEndedTextSubmission = (this.openEndedSubmissionType === 'text')
      if (this.isOpenEndedTextSubmission) {
        this.openEndedSubmissionType = `${this.questions[currentPage - 1].open_ended_text_editor} text`
        if (this.user.role === 2) {
          this.openEndedDefaultTextForm.open_ended_default_text = this.questions[currentPage - 1].open_ended_default_text
        } else {
          this.textSubmissionForm.text_submission = this.questions[currentPage - 1].submission
            ? await this.getTextFromS3(this.questions[currentPage - 1])
            : this.questions[currentPage - 1].open_ended_default_text
        }
      }
      this.isOpenEnded = this.isOpenEndedFileSubmission || this.isOpenEndedTextSubmission || this.isOpenEndedAudioSubmission

      this.$nextTick(() => {
        this.questionPointsForm.points = this.questions[currentPage - 1].points
        this.questionWeightForm.weight = this.questions[currentPage - 1].weight
        this.typesetMath()
      })

      if (this.showAssignmentStatistics) {
        this.getScoresSummary = getScoresSummary
        try {
          this.loaded = false
          this.chartdata = await this.getScoresSummary(this.assignmentId, `/api/scores/summary/${this.assignmentId}/${this.questions[this.currentPage - 1].id}`)
          this.loaded = true
        } catch (error) {
          this.$noty.error(error.message)
        }
      }
      this.autoAttributionHTML = ''
      let vm = this
      this.updateAutoAttribution(vm, this.questions[this.currentPage - 1])
      await this.setQuestionUpdatedAtSession(this.questions[this.currentPage - 1].loaded_question_updated_at)
      if (this.user.role === 3) {
        await this.canSubmit()
      }
      this.isLoading = false
      if (this.questions[this.currentPage - 1].qti_json || this.questions[this.currentPage - 1].a11y_qti_json) {
        this.showQtiJsonQuestionViewer = true
      }
      this.$nextTick(() => {
        const sourceDiv = document.getElementById('action-icons')
        const targetDiv = document.getElementById('instructor-action-icons')
        if (sourceDiv && targetDiv && sourceDiv.childNodes.length) {
          targetDiv.append(...sourceDiv.childNodes)
        }
      })
      if (this.questions[this.currentPage - 1].qti_json || this.questions[this.currentPage - 1].a11y_qti_json) {
        this.showQtiJsonQuestionViewer = true
      }
      setTimeout(() => {
        this.extraWaitForRenderComplete = true
      }, 2000)
      await this.getEmailToGraderSubject()
      if (this.autoSaveInterval) {
        clearInterval(this.autoSaveInterval)
        this.autoSaveInterval = null
      }
      if (this.isOpenEndedTextSubmission && this.user.role === 3 && !this.isAnonymousUser) {
        this.autoSaveInterval = setInterval(() => {
          if (this.textSubmissionForm.text_submission && this.submitButtonActive) {
            this.submitText(false)
          }
        }, 10000)
      }
    },
    async updateReviewQuestionTime () {
      try {
        let reviewQuestionTimeSpent = JSON.parse(localStorage.reviewQuestionTimeSpent)
        reviewQuestionTimeSpent.end = this.$moment()
        localStorage.reviewQuestionTimeSpent = JSON.stringify(reviewQuestionTimeSpent)
        console.log(localStorage.reviewQuestionTimeSpent)
      } catch (error) {
        console.log('Could not update Time Spent')
      }
    },
    async setQuestionUpdatedAtSession (loadedQuestionUpdatedAt) {
      try {
        const { data } = await axios.post(`/api/questions/set-question-updated-at-session`,
          { loaded_question_updated_at: loadedQuestionUpdatedAt })
        if (data.type === 'error') {
          this.$noty.error(data.message)
          return false
        }
      } catch (error) {
        this.$noty.error(error.message)
      }
    },
    async getTextFromS3 (question) {
      try {
        const { data } = await axios.post(`/api/submission-files/get-files-from-s3/${this.assignmentId}/${question.id}/${this.user.id}`,
          { open_ended_submission_type: 'text' })
        if (data.type === 'error') {
          this.$noty.error(data.message)
          return false
        }
        return data.files.submission_text
      } catch (error) {
        this.$noty.error(error.message)
      }
    },
    async getAssignmentInfo () {
      try {
        const { data } = await axios.get(`/api/assignments/${this.assignmentId}/view-questions-info`)
        if (data.type === 'error') {
          if (data.message === 'no assignment and grades url') {
            this.$bvModal.show('modal-no-assignment-and-grades-url')
            return false
          }
          if (data.message === 'You are not allowed to access this assignment.') {
            this.isLMS = data.is_lms
            this.$bvModal.show('modal-enroll-in-course')
            this.modalEnrollInCourseIsShown = true
          } else {
            this.$noty.error(data.message)
          }
          return false
        }
        if (!this.modalEnrollInCourseIsShown && this.inIFrame) {
          $('#default-padding-top').css('padding-top', '0')
          $('#skip-link').remove()
        }
        let assignment = data.assignment
        this.assignmentCanSubmitWork = assignment.can_submit_work
        this.canContactInstructorAutoGraded = assignment.can_contact_instructor_auto_graded
        this.algorithmicAssignment = Boolean(assignment.algorithmic)
        this.canContactGrader = assignment.can_contact_grader
        this.betaAssignmentsExist = assignment.beta_assignments_exist
        this.isBetaAssignment = assignment.is_beta_assignment
        this.isFormative = assignment.is_formative_course || assignment.formative
        this.scoringType = assignment.scoring_type
        this.hintPenaltyIfShownHint = assignment.hint_penalty
        this.questionNumbersShownInIframe = assignment.question_numbers_shown_in_iframe
        this.submittedWorkFormatOptions = assignment.submitted_work_format
        this.submittedWorkPolicy = assignment.submitted_work_policy

        this.questionNumbersShownOutOfIframe = this.user.role !== 3 || (assignment.question_url_view === 'assignment' || (assignment.question_url_view === 'question' && !this.$route.params.questionId))
        if (this.user.role === 3) {
          if (this.isLMS && !assignment.lti_launch_exists) {
            this.launchThroughLMSMessage = true
          } else if (!assignment.available || !assignment.shown) {
            this.availableOn = assignment.available_on
            this.assignmentShown = assignment.shown
            this.cannotViewAssessmentMessage = true
          }
          this.title = `${assignment.name}`
          this.fullPdfUrl = assignment.full_pdf_url
          this.showCurrentFullPDF = !!this.fullPdfUrl.length
        }
        this.isAnonymousUser = assignment.is_anonymous_user

        if (this.user.role === 2) {
          this.questionView = assignment.question_view
        }
        this.name = assignment.name
        this.pastDue = assignment.past_due

        if (this.user.role === 3) {
          this.questionStartTime = this.$moment().unix()
          this.startTimeInactive = 0
        }

        this.assessmentType = assignment.assessment_type
        this.numberOfAllowedAttempts = assignment.number_of_allowed_attempts
        this.numberOfAllowedAttemptsPenalty = assignment.number_of_allowed_attempts_penalty
        if (this.assessmentType !== 'clicker') {
          this.presentationMode = false
        }
        this.capitalFormattedAssessmentType = this.assessmentType === 'learning tree' ? 'Learning Trees' : 'Questions'
        this.has_submissions_or_file_submissions = assignment.has_submissions_or_file_submissions
        if (this.assessmentType !== 'clicker') {
          this.timeLeft = +assignment.time_left
        } else {
          this.defaultClickerTimeToSubmit = assignment.default_clicker_time_to_submit
          this.clickerTimeForm.time_to_submit = this.defaultClickerTimeToSubmit
        }

        this.totalPoints = parseInt(String(assignment.total_points).replace(/\.00$/, ''))
        this.source = assignment.source
        this.compiledPDF = assignment.file_upload_mode === 'compiled_pdf'
        this.bothFileUploadMode = assignment.file_upload_mode === 'both'
        this.openEndedSubmissionTypeAllowed = (assignment.assessment_type === 'delayed')// can upload at the question level
        this.solutionsReleased = Boolean(Number(assignment.solutions_released))
        this.latePolicy = assignment.late_policy
        this.showScores = Boolean(Number(assignment.show_scores))
        this.scoring_type = assignment.scoring_type
        this.students_can_view_assignment_statistics = assignment.students_can_view_assignment_statistics
        this.showPointsPerQuestion = assignment.show_points_per_question
        this.showUpdatePointsPerQuestion = assignment.points_per_question === 'number of points'
        this.canViewHintAtAssignmentLevel = assignment.can_view_hint
        this.flashcardSettings = assignment.flashcard_settings || (assignment.assessment_type === 'flashcard' ? {
          autoplay: { enabled: true, seconds: 4, student_override: true },
          random_shuffle: { enabled: true, student_override: true },
          show_hint: { enabled: true, student_override: true },
          text_to_speech: { enabled: false, student_override: true },
          captions: { enabled: false, student_override: true }
        } : null)
      } catch (error) {
        if (error.message.includes('status code 404')) {
          this.showInvalidAssignmentMessage = true
          return false
        } else {
          this.$noty.error(error.message)
        }
        this.title = 'Assessments'
      }
      return true
    },
    async setCutupAsSolution (questionId) {
      if (this.settingAsSolution) {
        this.$noty.info('Please be patient while your request is being processed.')
        return false
      }
      this.settingAsSolution = true
      try {
        const { data } = await this.cutupsForm.patch(`/api/cutups/${this.assignmentId}/${questionId}/solution`)

        this.settingAsSolution = false
        if (data.type === 'success') {
          this.$noty.success(data.message)
          this.$bvModal.hide('modal-upload-file')

          if (this.user.role === 2) {
            this.questions[this.currentPage - 1].solution = data.cutup
            this.questions[this.currentPage - 1].solution_file_url = data.solution_file_url
            this.questions[this.currentPage - 1].solution_type = 'q'
          }
          this.questions[this.currentPage - 1].date_submitted = data.date_submitted
        } else {
          this.cutupsForm.errors.set('chosen_cutups', data.message)
        }
      } catch (error) {
        this.$noty.error('We could not set this cutup as your solution.  Please try again or contact us for assistance.')
      }
    },
    async getCutups (assignmentId) {
      try {
        const { data } = await axios.get(`/api/cutups/${assignmentId}`)
        if (data.type === 'error') {
          this.$noty.error(data.message)
          return false
        }
        this.cutups = data.cutups
        this.showCurrentFullPDF = this.cutups.length
      } catch (error) {
        this.$noty.error('We could not retrieve your cutups for this assignment.  Please refresh the page and try again.')
      }
    },
    async getSelectedQuestions (assignmentId, questionId) {
      try {
        const { data } = await axios.get(`/api/assignments/${assignmentId}/questions/view`)
        if (data.type === 'error') {
          this.$noty.error(data.message)
          return false
        }

        this.questions = data.questions
        this.isInstructorLoggedInAsStudent = data.is_instructor_logged_in_as_student
        this.isInstructorWithAnonymousView = data.is_instructor_with_anonymous_view
        if (this.isInstructor()) {
          await this.getMyFavoriteQuestions()
        }
        if (!this.questions.length) {
          this.initializing = false
          return false
        }

        if (this.questionId) {
          if (!this.initCurrentPage(this.questionId)) {
            this.showQuestionDoesNotExistMessage = true
            this.isLoading = false
            return false
          }
        }

        this.questionPointsForm.points = this.questions[this.currentPage - 1].points
        this.questionWeightForm.weight = this.questions[this.currentPage - 1].weight

        this.initializing = false
      } catch (error) {
        this.$noty.error(`We could not retrieve the questions for this assignment: ${error.message}.  Please try again or contact us for assistance.`)
      }
      this.$nextTick(() => {
        this.showIframe(this.questions[this.currentPage - 1].iframe_id)
      })
    },
    syncFlashcardPage (questionId) {
      const idx = this.questions.findIndex(q => parseInt(q.id) === parseInt(questionId))
      if (idx !== -1) this.currentPage = idx + 1
    },
    onFlashcardControlsChanged (state) {
      this.fcControls = { ...this.fcControls, ...state }
    },
    initCurrentPage () {
      let questionExistsInAssignment = false
      // console.log(this.questions)
      for (let i = 0; i <= this.questions.length - 1; i++) {
        if (parseInt(this.questions[i].id) === parseInt(this.questionId)) {
          this.currentPage = i + 1
          questionExistsInAssignment = true
        }
      }
      return questionExistsInAssignment
    },
    metaInfo () {
      return { title: 'Assignment Questions' }
    }
  }
}
</script>
<style scoped>
.radio-wrapper {
  display: flex;
  justify-content: center;
  margin-top: 2rem; /* space from top */
}

.radio-label {
  display: inline-flex;
  align-items: center;
  font-size: 1.5rem;
  margin-right: 2rem; /* space between options */
}

.big-radio {
  width: 2rem;
  height: 2rem;
  margin-right: 1rem;
}

.timer-wrapper {
  display: inline-block;
  width: 250px; /* Set a fixed width wide enough for the max-length value */
}

.big-close {
  font-size: 3rem;
  line-height: 1;
}

.v-enter-active,
.v-leave-active {
  transition: opacity .75s ease;
}

.v-enter-from,
.v-leave-to {
  opacity: 0;
}

#cke_bottom {
  height: 9px;
}

.example-drag label.btn {
  margin-bottom: 0;
  margin-right: 1rem;
}

.example-drag .drop-active {
  top: 0;
  bottom: 0;
  right: 0;
  left: 0;
  position: fixed;
  z-index: 9999;
  opacity: .6;
  text-align: center;
  background: #000;
}

.example-drag .drop-active h3 {
  margin: -.5em 0 0;
  position: absolute;
  top: 50%;
  left: 0;
  right: 0;
  -webkit-transform: translateY(-50%);
  -ms-transform: translateY(-50%);
  transform: translateY(-50%);
  font-size: 40px;
  color: #fff;
  padding: 0;
}
</style>
<style>
div.ar-icon svg {
  vertical-align: top !important;
}

.sidebar-card {
  width: 368px;
}

#modal-instructor-clicker-question___BV_modal_backdrop_ {
  backdrop-filter: blur(5px);
  background-color: rgba(0, 0, 0, 0.5);
  opacity: 1 !important;
}

.modal .modal-huge {
  max-width: 90%;
  width: 90%;
}

#modal-instructor-clicker-question___BV_modal_body_ {
  padding-top: 0px;
}

#modal-instructor-clicker-question___BV_modal_header_.modal-header {
  padding-bottom: 0px;
  border: 0px;
}

.clicker-pagination .page-item.active .page-link {
  background-color: transparent !important;
  border-color: #dee2e6;
  box-shadow: none;
  color: inherit;
}

.clicker-pagination .page-link {
  color: inherit;
}

.no-card-style {
  border: none !important;
  background: none !important;
  box-shadow: none !important;
}

.no-card-style .card-header {
  background: none !important;
  border: none !important;
  padding: 0 !important;
}

.no-card-style .card-body {
  padding: 0 !important;
}
</style>
