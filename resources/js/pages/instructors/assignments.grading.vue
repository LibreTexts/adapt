<template>
  <div>
    <AllFormErrors :all-form-errors="allFormErrors" :modal-id="'modal-errors-canned-response'"/>
    <AllFormErrors :all-form-errors="allFormErrors" :modal-id="'modal-errors-grading-form'"/>

    <b-modal id="modal-submitted-work"
             :title="`Submitted on ${submittedWork.submitted_at}`"
             size="xl"
    >
      <b-embed
        v-resize="{ log: false, checkOrigin: false }"
        width="100%"
        :src="submittedWork.url"
        allowfullscreen
      />
      <template #modal-footer>
        <b-button
          size="sm"
          variant="primary"
          class="float-right"
          @click="$bvModal.hide('modal-submitted-work')"
        >
          OK
        </b-button>
      </template>
    </b-modal>
    <b-modal id="modal-structure-image"
             title="Scanned Image"
             size="xl"
    >
      <div class="font-weight-bold">{{ scannedImageMessage(grading[currentStudentPage - 1]) }}</div>
      <b-embed
        v-resize="{ log: false, checkOrigin: false }"
        width="100%"
        :src="structureImageTemporaryUrl"
        allowfullscreen
      />
      <template #modal-footer>
        <b-button
          size="sm"
          variant="primary"
          class="float-right"
          @click="$bvModal.hide('modal-structure-image')"
        >
          OK
        </b-button>
      </template>
    </b-modal>
    <b-modal id="modal-manual-grading-error"
             title="Incorrect Open-Ended Submission Type"
             hide-footer
    >
      <p>
        You've set the Open-Ended Submission Type for this question in your assignment to something other
        that "manual" but it looks like there are no student files associated with this question.
      </p>
      <p>
        Please <a :href="`/assignments/${this.assignmentId}/questions/view/${this.questionView}`" target="_blank">visit
        the question</a>
        and update the Open-Ended Submission Type to one of the manual options.
      </p>
    </b-modal>
    <b-modal id="modal-manage-student-timer"
             :title="`Manage Timer - ${grading.length ? grading[currentStudentPage - 1].student.name : ''}`"
             hide-footer
             @show="resetTimerModalInputs"
    >
      <p>
        <span class="font-weight-bold">Time Limit:</span> {{ studentTimeLimitStatus.time_limit }}<br>
        <span v-if="studentTimeLimitStatus.started_at">
          <span class="font-weight-bold">Started:</span>
          {{ $moment(studentTimeLimitStatus.started_at).format('M/D/YY h:mm A') }}<br>
          <span class="font-weight-bold">Expires:</span>
          {{ $moment(studentTimeLimitStatus.expires_at).format('M/D/YY h:mm A') }}
          <span :class="studentTimeLimitStatus.seconds_left <= 0 ? 'text-danger' : 'text-success'">
            (<countdown v-if="studentTimeLimitStatus.seconds_left > 0" :time="studentTimeLimitStatus.seconds_left * 1000" @end="getStudentTimeLimitStatus">
              <template v-slot="props">{{ formattedCountdownText(props) }} remaining</template>
            </countdown>
            <template v-else>Expired</template>)
          </span>
        </span>
        <span v-else>This student has not started the assignment yet.</span>
      </p>
      <hr>
      <b-form-group label-size="sm">
        <template #label>
          Add minutes to the current clock
          <QuestionCircleTooltip id="add-time-tooltip"/>
          <b-tooltip target="add-time-tooltip" delay="250" triggers="hover focus">
            Adds time to whatever's currently running. The fastest option for a quick, small extension - no need
            to know or calculate the current total.
          </b-tooltip>
        </template>
        <b-input-group>
          <b-form-input v-model.number="minutesToAddForStudent" type="number" min="1" size="sm"/>
          <b-input-group-append>
            <b-button size="sm" variant="primary" :disabled="addingTimeForStudent || !studentTimeLimitStatus.started_at" @click="addTimeForStudent">
              Add Time
            </b-button>
          </b-input-group-append>
        </b-input-group>
        <small v-if="!studentTimeLimitStatus.started_at" class="text-muted">
          This student hasn't started yet, so there's no running clock to add time to. Use Set Time below to start
          one on their behalf.
        </small>
      </b-form-group>
      <b-form-group label-size="sm">
        <template #label>
          Set a new total duration (e.g. '1 hour', '90 seconds')
          <QuestionCircleTooltip id="set-time-tooltip"/>
          <b-tooltip target="set-time-tooltip" delay="250" triggers="hover focus">
            Sets a brand-new total duration, replacing the current clock. If the student hasn't started yet, this
            starts their clock immediately, right now - unlike Reset, it doesn't wait for them to click Start.
          </b-tooltip>
        </template>
        <b-input-group>
          <b-form-input v-model="newTimeLimitForStudent" type="text" size="sm" placeholder="e.g. 1 hour"
                        :state="newTimeLimitForStudent ? looksLikeValidDuration(newTimeLimitForStudent) : null"
          />
          <b-input-group-append>
            <b-button size="sm" variant="primary"
                      :disabled="settingTimeForStudent || !newTimeLimitForStudent || !looksLikeValidDuration(newTimeLimitForStudent)"
                      @click="setTimeForStudent"
            >
              Set Time
            </b-button>
          </b-input-group-append>
          <b-form-invalid-feedback :state="newTimeLimitForStudent ? looksLikeValidDuration(newTimeLimitForStudent) : null">
            Enter a duration like "1 hour", "90 seconds", or "2 minutes".
          </b-form-invalid-feedback>
        </b-input-group>
      </b-form-group>
      <hr>
      <b-button size="sm" variant="outline-danger" :disabled="resettingStudentTimer" @click="resetStudentTimer">
        Reset Timer Entirely
      </b-button>
      <QuestionCircleTooltip id="reset-timer-modal-tooltip"/>
      <b-tooltip target="reset-timer-modal-tooltip" delay="250" triggers="hover focus">
        Wipes the clock completely. The student sees the Start Assignment screen again and can begin on their own
        timing whenever they're ready - unlike Set Time, which starts the clock immediately.
      </b-tooltip>
    </b-modal>
    <b-modal id="modal-confirm-timer-action"
             title="Please Confirm"
             ok-title="Continue"
             cancel-title="Cancel"
             button-size="sm"
             @ok="runPendingTimerConfirmAction"
    >
      <p class="mb-0">{{ confirmTimerModalMessage }}</p>
    </b-modal>
    <b-modal id="modal-discussion"
             :title="`Started by ${activeDiscussion.started_by} on ${activeDiscussion.created_at}`"
             no-close-on-backdrop
             size="lg"
    >
      <div v-for="(comment, commentIndex) in activeDiscussion.comments" :key="`active-discussion-${commentIndex}`">
        <span class="text-muted">{{ comment.created_at }}</span> <span class="font-weight-bold"
      >{{ comment.created_by_name }}</span> <span v-show="comment.created_by_user_id === activeUserId"
                                                  class="text-success"
      >***</span>
        <span v-if="comment.text" v-html="comment.text"/>
        <iframe
          v-if="comment.file"
          v-resize="{ log: false }"
          :src="`/discussion-comments/media-player/discussion-comment-id/${comment.id}/is-phone/0`"
          width="100%"
          frameborder="0"
          allowfullscreen=""
        />
        <hr>
      </div>
      <template #modal-footer>
        <b-button
          size="sm"
          class="float-right"
          @click="$bvModal.hide('modal-discussion')"
        >
          OK
        </b-button>
      </template>
    </b-modal>
    <div class="vld-parent">
      <ModalOverrideSubmissionScore :active-submission-score="activeSubmissionScore"
                                    :override-submission-score-form="overrideSubmissionScoreForm"
                                    :first-last="firstLast"
                                    :original-score="originalScore"
                                    :question-title="questionTitle"
                                    @reloadSubmissionScores="reloadSubmissionOverrideScore"
      />

      <loading :active.sync="isLoading"
               :can-cancel="true"
               :is-full-page="true"
               :width="128"
               :height="128"
               color="#007BFF"
               background="#FFFFFF"
      />
      <b-modal
        id="modal-edit-canned-responses"
        ref="modal"
        title="Edit Canned Responses"
        size="lg"
      >
        <b-list-group-item
          v-for="cannedResponse in cannedResponses"
          :key="cannedResponse.id"
          class="flex-column align-items-start"
        >
          {{ cannedResponse.canned_response }}
          <a href="" @click.prevent="removeCannedResponse(cannedResponse.id)">
            <b-icon icon="trash"
                    :aria-label="`Remove canned response ${cannedResponse.canned_response}`"
                    class="text-muted"
            />
          </a>
        </b-list-group-item>
        <b-input-group class="mt-4">
          <b-form-input id="canned_response"
                        v-model="cannedResponseForm.canned_response"
                        aria-label="canned response"
                        type="text"
                        :class="{ 'is-invalid': cannedResponseForm.errors.has('canned_response') }"
                        @keydown="cannedResponseForm.errors.clear('canned_response')"
          />
          <b-input-group-append>
            <b-button variant="primary" size="sm" @click="submitCannedResponseForm">
              Save Response
            </b-button>
          </b-input-group-append>
          <has-error :form="cannedResponseForm" field="canned_response"/>
        </b-input-group>
        <template #modal-footer="{ ok }">
          <b-button size="sm" variant="success" @click="ok()">
            OK
          </b-button>
        </template>
      </b-modal>

      <b-modal
        id="modal-upload-file"
        hide-footer
        title="Upload Feedback File"
        size="lg"
      >
        <b-form-radio-group v-model="uploadFeedbackFileType"
                            label="Type"
                            class="mb-2"
        >
          <b-form-radio value="pdf-image">
            PDF/Image
          </b-form-radio>
          <b-form-radio v-model="uploadFeedbackFileType" value="audio">
            Audio
          </b-form-radio>
          <hr>
        </b-form-radio-group>
        <div v-if="uploadFeedbackFileType === 'pdf-image'">
          <b-form ref="form">
            <p>Accepted file types are: {{ getAcceptedFileTypes() }}.</p>
            <b-form-file
              ref="fileFeedbackInput"
              v-model="fileFeedbackForm.fileFeedback"
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
              {{ fileFeedbackForm.errors.get('fileFeedback') }}
            </div>
            <hr>
            <b-row align-h="end" class="mr-2">
              <b-button class="mr-2" size="sm" @click="handleCancel">
                Cancel
              </b-button>
              <b-button variant="primary" size="sm" @click="handleOk">
                Submit
              </b-button>
            </b-row>
          </b-form>
        </div>
        <div v-if="uploadFeedbackFileType === 'audio'">
          <audio-recorder
            ref="recorder"
            class="m-auto"
            :upload-url="audioFeedbackUploadUrl"
            :time="1"
            :successful-upload="submittedAudioFeedbackUpload"
            :failed-upload="failedAudioFeedbackUpload"
          />
        </div>
      </b-modal>
      <b-modal id="modal-instructions"
               title="Instructions"
               size="lg"
               no-close-on-backdrop
      >
        <p>
          For each student, please enter a submission score for the open-ended
          component and optionally
          add comments in the form of text or a file upload. The total number of points that the student receives
          for this questions will be the sum of the points that they received for submitting any automatically
          graded responses (Question Submission Score)
          plus the number of points that you give them for their file submission (File Submission Score).
        </p>
        <p>
          You can
          move through the course roster by using the right/left arrows, searching for students by name, or by
          clicking on individual student numbers.
        </p>
        <template #modal-footer>
          <b-button
            variant="primary"
            size="sm"
            class="float-right"
            @click="$bvModal.hide('modal-instructions')"
          >
            OK
          </b-button>
        </template>
      </b-modal>
      <div v-if="!isLoading">
        <PageTitle :title="title"/>
        <div v-if="grading.length>0">
          <b-container class="pb-3">
            <b-row class="d-inline-flex">
              <b-button size="sm" variant="primary" @click="$bvModal.show('modal-instructions')">
                Instructions
              </b-button>
              <span class="ml-2">
                <ConsultInsight
                  :url="'https://commons.libretexts.org/insight/open-grader'"
                />
              </span>
            </b-row>
          </b-container>
          <b-form-group
            v-if="user.id === 5"
            id="ferpa"
            label-cols-sm="2"
            label-cols-lg="1"
            label="FERPA Mode"
            label-for="FERPA Mode"
            label-size="sm"
          >
            <toggle-button
              class="mt-2"
              :width="55"
              :value="ferpaMode"
              :sync="true"
              :font-size="14"
              :margin="4"
              :color="toggleColors"
              :labels="{checked: 'On', unchecked: 'Off'}"
              size="sm"
              @change="submitFerpaMode()"
            />
          </b-form-group>
          <b-form-group
            id="renderMathJax"
            label-cols-sm="2"
            label-cols-lg="1"
            label-for="MathJax"
            label-size="sm"
          >
            <template #label>
              MathJax
              <QuestionCircleTooltip id="mathjax"/>
              <b-tooltip target="mathjax"
                         delay="250"
                         triggers="hover focus"
              >
                Render student submissions that contain mathematical notation
              </b-tooltip>
            </template>
            <toggle-button
              class="mt-2"
              :width="55"
              :value="renderMathJax"
              :sync="true"
              :font-size="14"
              :margin="4"
              :color="toggleColors"
              :labels="{checked: 'On', unchecked: 'Off'}"
              size="sm"
              @change="updateRenderMathJax()"
            />
          </b-form-group>
          <b-form-group
            v-if="hasMultipleSections"
            label-cols-sm="2"
            label-cols-lg="1"
            label="Section View"
            label-for="section_view"
            label-size="sm"
          >
            <b-form-row>
              <b-col lg="3">
                <b-form-select
                  id="section_view"
                  v-model="sectionId"
                  size="sm"
                  :options="sections"
                  @change="processing=true;getGrading()"
                />
              </b-col>
            </b-form-row>
          </b-form-group>
          <b-form-group
            label-cols-sm="2"
            label-cols-lg="1"
            label="Filter"
            label-for="filter"
            label-size="sm"
          >
            <b-form-row>
              <b-col sm="4">
                <b-form-select
                  id="filter"
                  v-model="gradeView"
                  title="Grade view"
                  :options="gradeViews"
                  size="sm"
                  @change="processing=true;getGrading()"
                />
              </b-col>
            </b-form-row>
          </b-form-group>
          <b-form-group
            label-cols-sm="2"
            label-cols-lg="1"
            label="Question"
            label-for="question_view"
            label-size="sm"
          >
            <b-form-row>
              <b-col lg="1">
                <b-form-select
                  id="question_view"
                  v-model="questionView"
                  :options="questionOptions"
                  size="sm"
                  @change="processing=true;getGrading()"
                />
              </b-col>
              <b-col lg="2">
                <span v-if="processing">
                  <b-spinner small type="grow"/>
                  Processing...
                </span>
              </b-col>
            </b-form-row>
          </b-form-group>
          <hr>
          <div v-if="!showNoAutoGradedOrOpenSubmissionsExistAlert">
            <div class="text-center h5">
              Student
            </div>
            <div class="overflow-auto">
              <b-pagination
                :key="currentStudentPage"
                v-model="currentStudentPage"
                :total-rows="numStudents"
                :per-page="perPage"
                align="center"
                first-number
                last-number
                limit="17"
                @input="changePage()"
              />
            </div>
            <div class="text-center">
              <b-container class="mb-2">
                <b-row class="justify-content-md-center">
                  <b-col v-if="user.role === 2 || (user.role === 4 && gradersCanSeeStudentNames)" cols="4">
                    <autocomplete
                      ref="searchStudent"
                      placeholder="Enter a student's name"
                      aria-label="Enter a student's name"
                      :search="searchByStudent"
                      @submit="setQuestionAndStudentByStudentName"
                    />
                  </b-col>
                </b-row>
                <b-row v-if="user.role === 4 && !gradersCanSeeStudentNames" class="justify-content-md-center">
                  <b-alert :show="true" variant="info">
                    <span class="font-weight-bold">
                      Your instructor has chosen to keep student names anonymous.  The names
                      you see below are randomly generated.
                    </span>
                  </b-alert>
                </b-row>
              </b-container>
              <b-button variant="outline-primary"
                        size="sm"
                        @click="visitQuestion"
              >
                Visit Question
              </b-button>
              <SolutionFileHtml :key="`solution-file-html-${questionView}`"
                                :questions="solutions"
                                :current-page="1"
                                :show-na="false"
              />
              <span v-if="qtiAnswerJson">
                <QtiJsonAnswerViewer
                  :modal-id="currentStudentPage"
                  :qti-json="qtiAnswerJson"
                />
                <b-button
                  size="sm"
                  variant="outline-info"
                  @click="$bvModal.show(`qti-answer-${currentStudentPage}`)"
                >
                  View Correct Answer
                </b-button>
              </span>

              <b-button variant="outline-primary"
                        size="sm"
                        @click="openRegrader"
              >
                Open Regrader
              </b-button>
              <b-button variant="outline-primary"
                        size="sm"
                        @click="openAssignmentGradebook"
              >
                Open Assignment Gradebook
              </b-button>
              <div v-if="studentTimeLimitStatus.time_limit" class="mt-2">
                <font-awesome-icon :icon="clockIcon"/>
                <span class="font-weight-bold">Time Limit ({{ studentTimeLimitStatus.time_limit }}):</span>
                <span v-if="studentTimeLimitStatus.started_at">
                  Started {{ $moment(studentTimeLimitStatus.started_at).format('M/D/YY h:mm A') }},
                  <span :class="studentTimeLimitStatus.seconds_left <= 0 ? 'text-danger' : 'text-success'">
                    <countdown v-if="studentTimeLimitStatus.seconds_left > 0" :time="studentTimeLimitStatus.seconds_left * 1000" @end="getStudentTimeLimitStatus">
                      <template v-slot="props">{{ formattedCountdownText(props) }} remaining</template>
                    </countdown>
                    <template v-else>Expired</template>
                  </span>
                  <span v-if="studentTimeLimitStatus.extended_past_due" class="text-warning">
                    (extended past due)
                  </span>
                </span>
                <span v-else>Not started</span>
                <b-button size="sm" variant="outline-info" class="ml-2" @click="$bvModal.show('modal-manage-student-timer')">
                  Manage Timer
                </b-button>
              </div>
            </div>
          </div>
          <hr>
          <b-container>
            <div
              v-if="grading[currentStudentPage - 1]['open_ended_submission']['late_file_submission'] !== false"
            >
              <b-alert
                :show="true"
                variant="warning"
              >
                <div class="alert-link">
                  The file submission was late by {{
                    grading[currentStudentPage - 1]['open_ended_submission']['late_file_submission']
                  }}.
                  <br>
                  <span v-if="latePolicy === 'deduction'">
                    According to the late policy, a deduction of {{ lateDeductionPercent }}% should be applied once
                    <span v-if="lateDeductionApplicationPeriod !== 'once'">
                      per "{{
                        lateDeductionApplicationPeriod
                      }}"</span> for a total deduction of {{
                      grading[currentStudentPage - 1]['open_ended_submission']['late_penalty_percent']
                    }}%.
                  </span>
                </div>
              </b-alert>
            </div>
            <b-row>
              <b-col>
                <b-card ref="questionCard" header="default" :header-html="questionHeader">
                  <b-card-text>
                    <div v-if="grading[currentStudentPage - 1]['technology_iframe']
                           && technology === 'h5p'
                           && grading[currentStudentPage - 1]['auto_graded_submission']['submission']"
                         class="border-bottom border-gray-200 pb-3"
                    >
                      <div v-if="grading[currentStudentPage - 1]['auto_graded_submission']
                        && grading[currentStudentPage - 1]['auto_graded_submission']['submission']"
                      >
                        <span class="font-weight-bold">Student Submission: </span> <span
                        v-html="grading[currentStudentPage - 1]['auto_graded_submission']['submission']"
                      />
                      </div>
                    </div>
                    <div v-if="grading[currentStudentPage - 1]['non_technology_iframe_src']">
                      <iframe
                        id="open_ended_question_text"
                        :key="`non-technology-iframe-${grading[currentStudentPage - 1]['non_technology_iframe_src']}`"
                        v-resize="{ log: false }"
                        aria-label="open_ended_question_text"
                        style="height: 30px"
                        width="100%"
                        scrolling="no"
                        :src="grading[currentStudentPage - 1]['non_technology_iframe_src']"
                        frameborder="0"
                      />
                    </div>
                    <div v-if="isDiscussIt">
                      <div v-show="showDiscussIt">
                        <ul v-show="discussItCompletionCriteria" style="list-style: none;">
                          <li>
                            <CompletedIcon
                              :completed="discussItRequirementsInfo.satisfied_min_number_of_initiated_discussion_threads_requirement"
                            />
                            <span
                              :class="discussItRequirementsInfo.satisfied_min_number_of_initiated_discussion_threads_requirement ? 'text-success' : 'text-danger'"
                            >
                                Initiated {{ discussItRequirementsInfo.number_of_initiated_discussion_threads }} discussion thread<span
                              v-if="+discussItRequirementsInfo.number_of_initiated_discussion_threads !== 1"
                            >s</span>,
                                with {{ discussItRequirementsInfo.min_number_of_initiated_discussion_threads }} required.
                              </span>
                          </li>
                          <li>
                            <CompletedIcon
                              :completed="discussItRequirementsInfo.satisfied_min_number_of_replies_requirement"
                            />
                            <span
                              :class="discussItRequirementsInfo.satisfied_min_number_of_replies_requirement ? 'text-success' : 'text-danger'"
                            >
                                Submitted {{
                                discussItRequirementsInfo.number_of_replies_that_satisfied_the_requirements
                              }}
                                {{
                                +discussItRequirementsInfo.number_of_replies_that_satisfied_the_requirements !== 1 ? 'replies' : 'reply'
                              }},
                                with {{ discussItRequirementsInfo.min_number_of_replies }} required.
                              </span>
                          </li>
                          <li v-show="discussItRequirementsInfo.min_number_of_initiate_or_reply_in_threads > 0">
                            <CompletedIcon
                              :completed="discussItRequirementsInfo.satisfied_min_number_of_initiate_or_reply_in_threads_requirement"
                            />
                            <span
                              :class="discussItRequirementsInfo.satisfied_min_number_of_initiate_or_reply_in_threads_requirement ? 'text-success' : 'text-danger'"
                            >
                                Initiated or replied to {{
                                discussItRequirementsInfo.number_of_initiate_or_reply_in_threads_that_satisfied_the_requirements
                              }} discussion thread<span
                              v-if="+discussItRequirementsInfo.number_of_initiate_or_reply_in_threads_that_satisfied_the_requirements !== 1"
                            >s</span>,
                                with {{ discussItRequirementsInfo.min_number_of_initiate_or_reply_in_threads }} required.
                              </span>
                          </li>

                        </ul>

                        <div
                          v-if="discussionsByUserId.find(value => value.user_id === grading[currentStudentPage - 1].student.user_id).comments"
                        >
                          <div
                            v-for="(comment,commentIndex) in discussionsByUserId.find(value => value.user_id === grading[currentStudentPage - 1].student.user_id).comments"
                            :key="`comments-${commentIndex}`"
                          >
                            <span v-show="discussItCompletionCriteria">
                                <span v-b-tooltip.hover="{ delay: { show: 500, hide: 0 } }"
                                      :title="satisfiedRequirement(comment.discussion_comment_id)
                                        ? 'This discussion comment satisfied the requirement.'
                                        : 'This discussion comment did not satisfy the requirement.'"
                                >
                                  <CompletedIcon
                                    :completed="satisfiedRequirement(comment.discussion_comment_id)"
                                  />
                                </span>
                              </span>
                            <a href=""
                               @click.prevent="showDiscussion(comment.discussion_id, grading[currentStudentPage - 1].student.user_id)"
                            >{{ comment.created_at }}:</a>
                            <b-alert :show="comment.pasted_comment === 1">
                              This comment was pasted in by the student.
                              <QuestionCircleTooltip :id="`pasted-comment-${comment.id}`"/>
                              <b-tooltip :target="`pasted-comment-${comment.id}`"
                                         delay="250"
                                         width="600"
                                         triggers="hover focus"
                                         custom-class="custom-tooltip"
                              >
                                If a student pastes in a comment, they are informed that you will be notified.<br><br>Comments
                                that were
                                pasted in may have first been written by the student in an application such as
                                Microsoft Word or the
                                student may have used ChatGPT to generate the comment.
                              </b-tooltip>
                            </b-alert>
                            <div v-if="comment.text" v-html="comment.text"/>

                            <b-tooltip :target="`view-in-new-tab-${comment.discussion_comment_id}`"
                                       triggers="hover focus" delay="500"
                            >
                              View in New Tab
                            </b-tooltip>
                            <span
                              v-show="comment.file"
                              :id="`view-in-new-tab-${comment.discussion_comment_id}`"
                              class="float-right primary"
                              style="cursor: pointer"
                              @click="viewInNewTab(`/discussion-comments/media-player/discussion-comment-id/${comment.discussion_comment_id}/is-phone/0`)"
                            >
                                <font-awesome-icon :icon="externalLink"/>
                              </span>
                            <iframe
                              v-if="comment.file"
                              :key="`discussion-comment-${discussionCommentCacheKey}`"
                              v-resize="{ log: false }"
                              :src="`/discussion-comments/media-player/discussion-comment-id/${comment.discussion_comment_id}/is-phone/0`"
                              width="100%"
                              frameborder="0"
                              allowfullscreen=""
                            />
                            <Transcript :key="`discussion-comment-id-${comment.id}`"
                                        :active-transcript="comment.transcript"
                                        :active-media="comment"
                                        :model="'DiscussionComment'"
                                        :in-modal="false"
                                        @updateTranscriptInMedia="updateTranscriptInComment"
                                        @setActiveMedia="setActiveComment"
                            />
                          </div>
                        </div>
                        <div v-else>
                          No comments have been submitted by this student.
                        </div>
                      </div>
                    </div>
                    <div v-else>
                      <b-button
                        v-show="grading[currentStudentPage - 1].auto_graded_submission
                          && grading[currentStudentPage - 1].auto_graded_submission.submission
                          && grading[currentStudentPage - 1].auto_graded_submission.submission.includes('structure_s3_key')"
                        variant="primary"
                        size="sm"
                        @click="viewStructureImage(grading[currentStudentPage - 1])"
                      >
                        View Scanned Image
                      </b-button>
                      <QtiJsonQuestionViewer :key="`qti-json-${currentStudentPage}`"
                                             :qti-json="grading[currentStudentPage - 1]['qti_json']"
                                             :show-qti-answer="true"
                                             :show-submit="false"
                                             :show-response-feedback="false"
                                             :student-response="getStudentResponse(grading[currentStudentPage - 1])"
                      />
                    </div>

                    <div v-if="grading[currentStudentPage - 1]['technology_iframe']">
                      <iframe
                        :key="`technology-iframe-${currentStudentPage}`"
                        v-resize="{ log: false }"
                        aria-label="auto_graded_submission_text"
                        width="100%"
                        allowtransparency="true"
                        :src="grading[currentStudentPage - 1]['technology_iframe']"
                        frameborder="0"
                        @load="receiveMessage"
                      />
                    </div>
                  </b-card-text>
                </b-card>
                <div
                  v-if="isForge"
                  class="mt-2"
                >
                  <b-card header="default"
                          :header-html="getForgeSubmissionHeader()"
                  >
                    <ForgeViewer
                      :key="`forge-viewer-${grading[currentStudentPage - 1]['open_ended_submission']['question_id']}-${grading[currentStudentPage - 1].student.user_id}`"
                      :type="'submissionViewer'"
                      :resubmission-enabled="+grading[currentStudentPage - 1]['open_ended_submission']['upload_count'] === 0"
                      :assignment-id="+assignmentId"
                      :show-forge-button="grading[currentStudentPage - 1]['open_ended_submission']['date_submitted'] !== null"
                      :question-id="grading[currentStudentPage - 1]['open_ended_submission']['question_id']"
                      :student-id="grading[currentStudentPage - 1].student.user_id"
                      @submissionExists="updateSubmissionExists"
                    />
                  </b-card>
                </div>
              </b-col>

              <b-col>
                <div class="mb-2">
                  <b-card
                    class="h-50"
                    :style="{ borderColor: cardBorderColor, borderWidth:'2px' }"
                  >
                    <template #header>
                      <h2 class="h7 mb-0">
                        Scores for {{ grading[currentStudentPage - 1]['student']['name'] }}
                        <QuestionCircleTooltip :id="`student-info`"/>
                        <b-tooltip :target="`student-info`"
                                   delay="250"
                                   width="600"
                                   triggers="hover focus"
                                   custom-class="custom-tooltip"
                        >
                          Student ID: {{ grading[currentStudentPage - 1]['student']['student_id'] }}<br>
                          Email: {{ grading[currentStudentPage - 1]['student']['email'] }}
                        </b-tooltip>
                      </h2>
                      <div v-if="grading[currentStudentPage - 1]['last_graded']">
                        <small>Updated {{ grading[currentStudentPage - 1]['last_graded'] }}</small>
                      </div>
                      <div v-else>
                        <small>A score has yet to be entered for this student.</small>
                      </div>
                    </template>
                    <b-card-text>
                      <b-form ref="form">
                        <div v-show="grading[currentStudentPage - 1]['submission_score_override']">
                          <b-alert show>
                            The student will see the override score for this question.
                          </b-alert>
                          <span class="pr-2"><strong>Override Score:</strong> {{
                              grading[currentStudentPage - 1]['submission_score_override']
                            }}
                          </span>
                          <b-button size="sm"
                                    variant="outline-primary"
                                    @click="initOverrideSubmissionScore(grading[currentStudentPage - 1])"
                          >
                            Update
                          </b-button>
                          <hr>
                        </div>
                        <b-form-group
                          v-if="!isDiscussIt && !isForge"
                          label-cols-sm="4"
                          label-cols-lg="3"
                          label-for="auto_graded_score"
                        >
                          <template v-slot:label>
                            <span class="font-weight-bold">Auto-graded<br/>score:</span>
                          </template>
                          <div v-show="isAutoGraded" class="pt-1">
                            <div class="d-flex align-items-center">
                              <b-form-input v-show="grading[currentStudentPage - 1]['auto_graded_submission']"
                                            id="auto_graded_score"
                                            v-model="gradingForm.question_submission_score"
                                            type="text"
                                            :aria-labelledby="questionSubmissionScoreErrorMessage.length ? 'question_submission_score_error' : ''"
                                            size="sm"
                                            style="width:75px"
                                            :class="{ 'is-invalid': questionSubmissionScoreErrorMessage.length }"
                                            @keydown="questionSubmissionScoreErrorMessage = ''"
                              />
                              <span v-show="grading[currentStudentPage - 1]['auto_graded_submission']"
                                    class="text-muted ml-2 mr-3"
                                    style="font-size: 0.9em; white-space: nowrap;"
                              >(Max: {{
                                  grading[currentStudentPage - 1]['open_ended_submission']['points'] * 1
                                }})</span>
                              <span
                                v-if="isAutoGraded && !isOpenEnded && grading[currentStudentPage - 1]['auto_graded_submission']"
                                class="d-inline-flex"
                              >
    <b-button size="sm"
              variant="outline-success"
              class="mr-2"
              @click="submitGradingForm(true,
                                        {
                                          scoreType: 'question_submission_score',
                                          score: grading[currentStudentPage - 1]['open_ended_submission']['points'] * 1
                                        })"
    >Full Score</b-button>
    <b-button size="sm"
              variant="outline-danger"
              @click="submitGradingForm(true,
                                        {
                                          scoreType: 'question_submission_score',
                                          score: 0
                                        })"
    >
      Zero Score</b-button>
  </span>
                            </div>

                            <div v-if="!grading[currentStudentPage - 1]['auto_graded_submission']"
                                 class="pt-1"
                            >
                              <span>No submission</span>
                            </div>
                          </div>
                          <div v-show="!isAutoGraded" class="pt-2">
                            <span>Not applicable</span>
                          </div>
                          <div v-if="questionSubmissionScoreErrorMessage"
                               id="question_submission_score_error"
                               class="text-danger"
                               style="font-size: 80%"
                          >
                            {{ questionSubmissionScoreErrorMessage }}
                          </div>
                        </b-form-group>
                        <div v-if="canSubmitWork" class="mb-2">
                        <span v-show="submittedWork.pending_score">
                          <strong>Approve score</strong>: <span
                          :class="{'text-success': submittedWork.url, 'text-danger': !submittedWork.url}"
                        >{{ roundToDecimalSigFig(submittedWork.pending_score, 4) }}
                          <span id="apply-pending-score-tooltip">
                       <b-button
                         :variant="submittedWork.url ? 'outline-success' : 'outline-danger'"
                         size="sm"
                         class="ml-1"
                         @click="applyPendingScore()"
                       >
  Approve
                       </b-button></span></span></span>
                          <span v-if="submittedWork.url">
                        <b-button @click="$bvModal.show('modal-submitted-work')"
                                  variant="primary"
                                  size="sm"
                        >
                          View Submitted Work
                        </b-button>
                      </span>
                          <span v-if="!submittedWork.url" class="text-danger">There is no submitted work.</span>

                          <b-tooltip v-if="!submittedWork.url"
                                     target="apply-pending-score-tooltip"
                                     delay="500"
                                     triggers="hover focus"
                          >You will be applying the score even though this student has not submitted work.
                          </b-tooltip>
                        </div>

                        <b-form-group
                          v-show="isOpenEnded || isDiscussIt || isForge"
                          label-cols-sm="5"
                          label-cols-lg="4"
                          label-for="open_ended_score"
                        >
                          <template v-slot:label>
                            <span class="font-weight-bold">{{
                                getScoreLabel()
                              }}<br/>score:</span>
                          </template>
                          <div class="pt-1">
                            <div class="d-flex align-items-center">
                              <b-form-input
                                v-show="isOpenEnded
    || (isForge && grading[currentStudentPage - 1]['open_ended_submission']['date_submitted'] )
      || discussionsByUserId.find(item => item.user_id === grading[currentStudentPage-1].student.user_id).comments"
                                id="open_ended_score"
                                v-model="gradingForm.file_submission_score"
                                type="text"
                                size="sm"
                                :aria-labelledby="fileSubmissionScoreErrorMessage.length ? 'file_submission_score_error' : ''"
                                style="width:75px"
                                :class="{ 'is-invalid': fileSubmissionScoreErrorMessage.length }"
                                @keydown="fileSubmissionScoreErrorMessage=''"
                              />
                              <span v-show="grading[currentStudentPage - 1]['open_ended_submission']"
                                    class="text-muted ml-2 mr-3"
                                    style="font-size: 0.9em; white-space: nowrap;"
                              >(Max: {{
                                  grading[currentStudentPage - 1]['open_ended_submission']['points'] * 1
                                }})</span>
                              <span
                                v-if="(isOpenEnded && !isAutoGraded)
    || (isForge && grading[currentStudentPage - 1]['open_ended_submission']['date_submitted'] )
      || (isDiscussIt && discussionsByUserId.find(item => item.user_id === grading[currentStudentPage-1].student.user_id).comments)"
                                class="d-inline-flex"
                              >
    <b-button size="sm"
              class="mr-2"
              variant="outline-success"
              @click="submitGradingForm(true,
                                        {
                                          specialScore: 'full score',
                                          scoreType: 'file_submission_score',
                                          score: grading[currentStudentPage - 1]['open_ended_submission']['points'] * 1
                                        })"
    >Full Score</b-button>
    <b-button size="sm"
              variant="outline-danger"
              @click="submitGradingForm(true,
                                        {
                                          specialScore: 'zero score',
                                          scoreType: 'file_submission_score',
                                          score: 0
                                        })"
    >
      Zero Score</b-button>
  </span>
                            </div>
                            <div
                              v-show="(isDiscussIt && !discussionsByUserId.find(item => item.user_id === grading[currentStudentPage-1].student.user_id).comments)"
                              class="pt-1"
                            >
                              No comments submitted
                            </div>
                          </div>
                          <div v-show="!isOpenEnded && !isDiscussIt && !isForge" class="pt-2">
                            <span>Not applicable</span>
                          </div>
                          <div v-if="fileSubmissionScoreErrorMessage"
                               id="file_submission_score_error"
                               class="text-danger"
                               style="font-size: 80%"
                          >
                            {{ fileSubmissionScoreErrorMessage }}
                          </div>
                        </b-form-group>
                        <b-form-group
                          v-if="grading[currentStudentPage - 1]['open_ended_submission']['late_file_submission']"
                          label-cols-sm="5"
                          label-cols-lg="4"
                          label-for="late_penalty_percent"
                        >
                          <template v-slot:label>
                            <span class="font-weight-bold">
                              Late Penalty:
                            </span>
                          </template>
                          <div class="mt-1 mb-1 d-flex">
                            <b-form-input
                              id="late_penalty_percent"
                              v-model="grading[currentStudentPage - 1]['open_ended_submission']['applied_late_penalty']"
                              type="text"
                              size="sm"
                              style="width:75px"
                            />
                            <b-button
                              size="sm"
                              variant="info"
                              class="ml-2"
                              @click="applyLatePenalty()"
                            >
                              Apply Late Penalty
                            </b-button>
                          </div>
                        </b-form-group>
                        <br>
                        <div
                          v-if="rubric && (isOpenEnded || isDiscussIt || isForge)"
                        >
                          <RubricPointsBreakdown
                            :key="`rubric-points-breakdown-${rubricPointsBreakdownIndex}-${grading[currentStudentPage - 1]['open_ended_submission']['user_id']}-${grading[currentStudentPage - 1]['open_ended_submission']['question_id']}`"
                            :user-id="grading[currentStudentPage - 1]['open_ended_submission']['user_id']"
                            :assignment-id="+assignmentId"
                            :original-rubric="rubric"
                            :question-id="grading[currentStudentPage - 1]['open_ended_submission']['question_id']"
                            :question-points="grading[currentStudentPage - 1]['open_ended_submission']['points'] * 1 -gradingForm.question_submission_score* 1"
                            @updateOpenEndedSubmissionScore="updateOpenEndedSubmissionScore"
                            @setRubricPointsBreakdown="setRubricPointsBreakdown"
                            @setOriginalRubricWithMaxes="setOriginalRubricWithMaxes"
                          />
                        </div>
                        <hr>
                        <b-container>
                          <b-row>
                            <b-col v-if="isOpenEndedFileSubmission">
                              <b-button variant="outline-primary"
                                        :disabled="grading[currentStudentPage - 1]['open_ended_submission']['submission_url'] === null"
                                        size="sm"
                                        @click="openInNewTab(getFullPdfUrlAtPage(grading[currentStudentPage - 1]['open_ended_submission']['submission_url'],grading[currentStudentPage - 1]['page']) )"
                              >
                                Open File Submission
                              </b-button>
                            </b-col>
                          </b-row>
                        </b-container>
                      </b-form>
                    </b-card-text>
                  </b-card>
                </div>
                <div class="mb-2">
                  <b-card header="default"
                          :header-html="getGraderFeedbackTitle()"
                  >
                    <b-card-text align="center">
                      <div v-show="isOpenEnded || isDiscussIt || isForge">
                        <div v-show="(isForge && grading[currentStudentPage-1]['open_ended_submission']['date_submitted']) || (isOpenEnded && grading[currentStudentPage - 1]['open_ended_submission']['submission'])
                          ||(isDiscussIt && discussionsByUserId.find(item =>item.user_id === grading[currentStudentPage-1].student.user_id).comments)"
                        >
                          <b-row class="mb-2">
                            <b-col>
                              <b-form-select id="text_feedback_mode"
                                             v-model="textFeedbackMode"
                                             title="Text Feedback Mode"
                                             :options="textFeedbackModeOptions"
                                             size="sm"
                              />
                            </b-col>
                            <b-col>
                              <b-button v-if="textFeedbackMode === 'canned_response'"
                                        variant="info"
                                        size="sm"
                                        @click="openEditCannedResponsesModal"
                              >
                                Edit Responses
                              </b-button>
                            </b-col>
                          </b-row>
                          <b-form ref="form">
                            <ckeditor v-if="textFeedbackMode === 'rich_text'"
                                      :key="`${currentQuestionPage}-${currentStudentPage}`"
                                      v-model="richTextFeedback"
                                      :config="richEditorConfig"
                                      tabindex="0"
                                      style="margin-bottom: 23px"
                                      rows="5"
                                      max-rows="5"
                                      :class="{ 'is-invalid': gradingForm.errors.has('textFeedback') }"
                                      @namespaceloaded="onCKEditorNamespaceLoaded"
                                      @ready="handleFixCKEditor()"
                            />
                            <b-form-textarea
                              v-if="textFeedbackMode === 'plain_text'"
                              id="text_comments"
                              v-model="plainTextFeedback"
                              style="margin-bottom: 23px"
                              placeholder="Enter something..."
                              rows="5"
                              max-rows="5"
                              :class="{ 'is-invalid': gradingForm.errors.has('textFeedback') }"
                              @keydown="gradingForm.errors.clear('textFeedback')"
                            />
                            <has-error :form="gradingForm" field="textFeedback"/>

                            <b-form-select v-if="textFeedbackMode === 'canned_response'"
                                           v-model="cannedResponse"
                                           title="Canned response"
                                           :options="cannedResponseOptions"
                                           class="mb-5"
                            />
                            <hr>
                            <b-row class="float-right">
                              <b-button
                                variant="primary"
                                size="sm"
                                @click="openUploadFileModal()"
                              >
                                Upload Feedback File
                              </b-button>

                              <b-button
                                :class="{ 'disabled': !viewSubmission}"
                                :aria-disabled="!viewSubmission"
                                size="sm"
                                class="ml-2 mr-4"
                                @click="!viewSubmission ? '' : toggleView()"
                              >
                                View Feedback File
                              </b-button>
                            </b-row>
                          </b-form>
                        </div>
                        <div
                          v-show="(isOpenEnded && !grading[currentStudentPage - 1]['open_ended_submission']['submission'])
                           || (isForge && !grading[currentStudentPage - 1]['open_ended_submission']['date_submitted'])"
                        >
                          <h4 class="pt-2">
                            <span class="text-muted">
                              There is no open-ended submission for which to provide feedback.
                            </span>
                          </h4>
                        </div>
                        <div
                          v-show="isDiscussIt && !discussionsByUserId.find(item =>item.user_id === grading[currentStudentPage-1].student.user_id).comments"
                        >
                          <h4 class="pt-5">
                            <span class="text-muted">
                              There are no comments for which to provide feedback.
                            </span>
                          </h4>
                        </div>
                      </div>
                      <div v-show="!isOpenEnded && !isDiscussIt && !isForge">
                        <h4 class="pt-5">
                          <span class="text-muted">
                            This panel is applicable to open-ended assessments.
                          </span>
                        </h4>
                      </div>
                    </b-card-text>
                  </b-card>
                </div>
                <div class="text-center pt-3 pb-3">
                  <b-button variant="primary"
                            :class="{ 'disabled': noSubmission && !gradingForm.file_submission_score}"
                            :aria-disabled="noSubmission && !gradingForm.file_submission_score"
                            size="sm"
                            class="ml-1 mr-1"
                            @click="noSubmission && !gradingForm.file_submission_score ? '' :submitGradingForm(false)"
                  >
                    Submit
                  </b-button>
                  <b-button
                    :class="{ 'disabled': currentStudentPage === numStudents || (noSubmission && !gradingForm.file_submission_score)}"
                    :aria-disabled="currentStudentPage === numStudents || (noSubmission && !gradingForm.file_submission_score)"
                    size="sm"
                    variant="success"
                    @click="currentStudentPage === numStudents || (noSubmission && !gradingForm.file_submission_score)? '' : submitGradingForm(true)"
                  >
                    Submit And Next
                  </b-button>
                </div>
              </b-col>
            </b-row>
          </b-container>
          <b-container>
            <b-row v-if="!isDiscussIt && submissionArray.length" class="mt-2">
              <b-col>
                <b-card header="default" :header-html="getSubmissionSummaryTitle()">
                  <SubmissionArray
                    :key="`submission-array-${+renderMathJax}`"
                    :submission-array="submissionArray"
                    :question-submission-array="submissionArray"
                    :question-id="+questionView"
                    :assignment-id="+assignmentId"
                    :technology="technology"
                    :scoring-type="scoringType"
                    :user-id="grading[currentStudentPage - 1].student.user_id"
                    :user-role="user.role"
                    :small-table="true"
                    :penalties="grading[currentStudentPage - 1]['penalties']"
                    :render-math-jax="renderMathJax"
                  />
                </b-card>
              </b-col>
              <b-col/>
            </b-row>
          </b-container>
          <b-container v-if="isOpenEnded && grading[currentStudentPage - 1]['open_ended_submission']['submission']">
            <b-row align-h="center" class="pb-2">
              <toggle-button
                v-if="grading[currentStudentPage - 1]['rubric_category_submission'].length"
                class="mt-2"
                :width="113"
                :value="fullView"
                :sync="true"
                :font-size="14"
                :margin="4"
                :color="toggleColors"
                :labels="{checked: 'Full View', unchecked: 'Rubric View'}"
                @change="updateFullView()"
              />
            </b-row>
          </b-container>
          <div v-if="!fullView && grading[currentStudentPage - 1]['rubric_category_submission'] && rubricCategories">
            <Report
              v-if="rubricCategories.length"
              :key="`lab-report-key-${grading[currentStudentPage - 1].student.user_id}-${questionView}`"
              :assignment-id="Number(assignmentId)"
              :question-id="Number(questionView)"
              :user-id="grading[currentStudentPage - 1].student.user_id"
              :rubric-categories="rubricCategories"
              :rubric-scale="rubricScale"
              :grading="true"
              :points="+grading[currentStudentPage - 1]['open_ended_submission']['points']"
              @saveOpenEndedScore="saveOpenEndedScore"
            />
          </div>
          <div v-show="retrievedFromS3" class="row mt-4 d-flex justify-content-center"
               :style="grading[currentStudentPage - 1]['open_ended_submission']['submission'] ? 'height:1000px' : ''"
          >
            <div v-show="viewSubmission">
              <div v-if="isOpenEnded && isAutoGraded">
                <hr>
              </div>
              <div v-if="isOpenEnded && grading[currentStudentPage - 1]['open_ended_submission']['submission']">
                <b-row v-if="fullView" align-h="center" class="pb-2">
                  <span class="font-weight-bold">Open-Ended Submission</span>
                </b-row>
              </div>
              <div
                v-if="(grading[currentStudentPage - 1]['open_ended_submission']['pasted_content'])"
              >
                <b-row v-if="fullView" align-h="center" class="pb-2">
                  <b-alert show>
                    This text submission was pasted in by the student.
                    <QuestionCircleTooltip id="pasted-submission-tooltip"/>
                    <b-tooltip target="pasted-submission-tooltip"
                               delay="250"
                               width="600"
                               triggers="hover focus"
                               custom-class="custom-tooltip"
                    >
                      If a student pastes in a text submission, they are informed that you will be notified.<br><br>Text
                      submissions that were
                      pasted in may have first been written by the student in an application such as Microsoft Word or
                      the
                      student may have used ChatGPT to generate the comment.
                    </b-tooltip>
                  </b-alert>
                </b-row>
              </div>
              <div
                v-if="(grading[currentStudentPage - 1]['open_ended_submission']['submission_url'])"
              >
                <div v-if="fullView && isOpenEndedFileSubmission" class="row">
                  <iframe :key="grading[currentStudentPage - 1]['open_ended_submission']['submission']"
                          title="Open-ended submission"
                          width="600"
                          height="600"
                          :src="getFullPdfUrlAtPage(grading[currentStudentPage - 1]['open_ended_submission']['submission_url'],grading[currentStudentPage - 1]['open_ended_submission']['page'])"
                  />
                </div>
                <div v-if="isOpenEndedAudioSubmission">
                  <b-card sub-title="Submission">
                    <audio-player
                      :src="grading[currentStudentPage - 1]['open_ended_submission']['submission_url']"
                    />
                  </b-card>
                </div>
              </div>
              <b-container
                v-if="isOpenEndedTextSubmission
                  && grading[currentStudentPage -1]['open_ended_submission']['submission_text']"
              >
                <b-card>
                  <b-card-body>
                    <b-card-text>
                      <span class="font-weight-bold"
                            v-html="grading[currentStudentPage - 1]['open_ended_submission']['submission_text']"
                      />
                    </b-card-text>
                  </b-card-body>
                </b-card>
              </b-container>
            </div>
            <div v-show="!viewSubmission">
              <iframe
                v-if="grading[currentStudentPage - 1]['open_ended_submission']['file_feedback_type'] !== 'audio'"
                width="600"
                height="600"
                :src="grading[currentStudentPage - 1]['open_ended_submission']['file_feedback_url']"
              />
              <b-card
                v-if="grading[currentStudentPage - 1]['open_ended_submission']['file_feedback_type'] === 'audio'"
                sub-title="Audio Feedback"
              >
                <audio-player
                  v-if="grading[currentStudentPage - 1]['open_ended_submission']['file_feedback_type'] === 'audio'"
                  :src="grading[currentStudentPage - 1]['open_ended_submission']['file_feedback_url']"
                />
              </b-card>
              <b-alert class="mt-1" :variant="audioFeedbackDataType" :show="showAudioFeedbackMessage">
                <span class="font-weight-bold">{{ audioFeedbackDataMessage }}</span>
              </b-alert>
            </div>
          </div>
        </div>
      </div>
      <div v-if="showNoAutoGradedOrOpenSubmissionsExistAlert" class="mt-4">
        <b-alert show variant="info">
          <span class="alert-link">
            There are no submissions for this view.</span>
          <b-button variant="outline-primary" size="sm" @click="resetView">
            Reset View
          </b-button>
        </b-alert>
      </div>
    </div>
  </div>
</template>

<script>
import { h5pResizer } from '~/helpers/H5PResizer'
import { addGlow } from '~/helpers/HandleTechnologyResponse'
import axios from 'axios'
import Form from 'vform'
import { downloadSubmissionFile, downloadSolutionFile, getFullPdfUrlAtPage } from '~/helpers/DownloadFiles'
import { getAcceptedFileTypes } from '~/helpers/UploadFiles'
import Loading from 'vue-loading-overlay'
import 'vue-loading-overlay/dist/vue-loading.css'
import Autocomplete from '@trevoreyre/autocomplete-vue'
import '@trevoreyre/autocomplete-vue/dist/style.css'
import Vue from 'vue'
import { ToggleButton } from 'vue-js-toggle-button'
import CKEditor from 'ckeditor4-vue'
import { mapGetters } from 'vuex'
import { fixCKEditor } from '~/helpers/accessibility/fixCKEditor'
import { fixInvalid } from '~/helpers/accessibility/FixInvalid'
import AllFormErrors from '~/components/AllFormErrors'
import SolutionFileHtml from '../../components/SolutionFileHtml'
import Report from '../../components/Report.vue'
import ModalOverrideSubmissionScore from '../../components/ModalOverrideSubmissionScore.vue'
import { h5pOnLoadCssUpdates, webworkOnLoadCssUpdates, applyWarningsVisibility } from '~/helpers/CSSUpdates'
import QtiJsonQuestionViewer from '../../components/QtiJsonQuestionViewer.vue'
import SubmissionArray from '../../components/SubmissionArray.vue'
import CompletedIcon from '../../components/CompletedIcon.vue'
import RubricPointsBreakdown from '../../components/RubricPointsBreakdown.vue'
import ConsultInsight from '../../components/ConsultInsight.vue'
import QtiJsonAnswerViewer from '../../components/QtiJsonAnswerViewer.vue'
import { faExternalLinkAlt, faClock } from '@fortawesome/free-solid-svg-icons'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { roundToDecimalSigFig } from '../../helpers/Math'
import ForgeViewer from '../../components/viewers/ForgeViewer.vue'
import Transcript from '../../components/Transcript.vue'

Vue.prototype.$http = axios // needed for the audio player
export default {
  middleware: 'auth',
  components: {
    Transcript,
    ForgeViewer,
    FontAwesomeIcon,
    QtiJsonAnswerViewer,
    ConsultInsight,
    RubricPointsBreakdown,
    CompletedIcon,
    SubmissionArray,
    QtiJsonQuestionViewer,
    ModalOverrideSubmissionScore,
    Report,
    SolutionFileHtml,
    Loading,
    ToggleButton,
    ckeditor: CKEditor.component,
    AllFormErrors,
    Autocomplete
  },
  metaInfo () {
    return { title: 'Assignment Grading' }
  },
  data: () => ({
    discussionCommentCacheKey: 0,
    activeDiscussionCommentId: 0,
    formattedForgeQuestionType: '',
    isForge: false,
    canSubmitWork: false,
    submittedWork: {},
    externalLink: faExternalLinkAlt,
    clockIcon: faClock,
    // Per-student time-limit management (Manage Timer modal below the
    // student pagination). Refetched every time the student changes.
    studentTimeLimitStatus: {},
    minutesToAddForStudent: null,
    addingTimeForStudent: false,
    newTimeLimitForStudent: '',
    settingTimeForStudent: false,
    resettingStudentTimer: false,
    confirmTimerModalMessage: '',
    pendingTimerConfirmAction: null,
    qtiAnswerJson: '',
    structureImageTemporaryUrl: '',
    scoreInputType: '',
    totalPercentage: 0,
    originalRubricWithMaxes: [],
    rubricPointsBreakdownIndex: 0,
    totalScore: 0,
    rubric: null,
    showRubricProperties: false,
    routeStudentUserId: null,
    routeQuestionId: null,
    discussItCompletionCriteria: false,
    renderMathJax: false,
    showDiscussIt: false,
    discussItRequirementsInfo: {},
    activeDiscussion: {},
    activeUserId: 0,
    discussions: [],
    discussionsByUserId: [],
    isDiscussIt: false,
    questionHeader: '<h2 class="h7 mb-0">Question</span></h2>',
    scoringType: '',
    submissionArray: [],
    isAlgorithmic: false,
    event: {},
    cardBorderColor: '',
    technology: '',
    uploadFeedbackFileTypeTitle: 'PDF/image',
    originalScore: '',
    questionTitle: '',
    firstLast: '',
    activeSubmissionScore: {},
    overrideSubmissionScoreForm: new Form({
      assignment_id: 0,
      question_id: 0,
      student_user_id: 0,
      first_last: '',
      question_title: '',
      score: 0
    }),
    rubricScale: '',
    rubricCategories: [],
    assignmentId: 0,
    fullView: true,
    solutions: [],
    allFormErrors: [],
    toggleColors: window.config.toggleColors,
    questionSubmissionScoreErrorMessage: '',
    fileSubmissionScoreErrorMessage: '',
    jumpToStudentsByNumber: [],
    studentNumberToJumpTo: '--',
    gradersCanSeeStudentNames: false,
    isIndividualGrading: true,
    noSubmission: false,
    isAutoGraded: false,
    isOpenEnded: false,
    ferpaMode: false,
    message: '',
    processing: false,
    questionView: '',
    questionOptions: [],
    richTextFeedback: null,
    plainTextFeedback: null,
    cannedResponse: null,
    cannedResponseOptions: [],
    cannedResponseForm: new Form({
      canned_response: ''
    }),
    cannedResponses: [],
    richEditorConfig: {
      toolbar: [
        { name: 'clipboard', items: ['Cut', 'Copy', '-', 'Undo', 'Redo'] },
        {
          name: 'basicstyles',
          items: ['Bold', 'Italic', 'Underline', 'Subscript', 'Superscript']
        }
      ],
      removeButtons: '',
      height: 100
    },
    textFeedbackMode: 'plain_text',
    textFeedbackModeOptions: [
      { text: 'Plain Text', value: 'plain_text' },
      { text: 'Rich Text', value: 'rich_text' },
      { text: 'Canned Response', value: 'canned_response' }
    ],
    retrievedFromS3: false,
    hasMultipleSections: false,
    jumpToStudent: '',
    students: [],
    audioFeedbackDataType: '',
    audioFeedbackDataMessage: '',
    showAudioFeedbackMessage: false,
    feedbackModalTitle: 'Upload Pdf/Image',
    uploadFeedbackFileType: 'pdf-image',
    audioFeedbackUploadUrl: '',
    isOpenEndedFileSubmission: false,
    isOpenEndedAudioSubmission: false,
    isOpenEndedTextSubmission: false,
    openEndedType: '',
    sections: [{ text: 'All Sections', value: 0 }],
    sectionId: 0,
    gradeViews: [
      { text: 'All Students', value: 'allStudents' },
      { text: 'Ungraded Open-Ended Submissions', value: 'ungradedOpenEndedSubmissions' },
      { text: 'Graded Open-Ended Submissions', value: 'gradedOpenEndedSubmissions' }
    ],
    latePolicy: null,
    lateDeductionApplicationPeriod: '',
    lateDeductionPercent: 0,
    isLoading: true,
    gradeView: 'allStudents',
    title: '',
    loaded: true,
    viewSubmission: true,
    showNoAutoGradedOrOpenSubmissionsExistAlert: false,
    uploading: false,
    currentQuestionPage: 1,
    currentStudentPage: 1,
    perPage: 1,
    numStudents: 0,
    grading: [],
    fileFeedbackForm: new Form({
      fileFeedback: null
    }),
    gradingForm: new Form({
      question_submission_score: null,
      file_submission_score: null,
      text_feedback_editor: 'plain',
      textFeedback: null
    })
  }),
  computed: mapGetters({
    user: 'auth/user'
  }),
  watch: {
    'gradingForm.file_submission_score' (newVal, oldVal) {
      this.totalScore = +this.gradingForm.question_submission_score + +newVal || 0
    },
    'gradingForm.question_submission_score' (newVal, oldVal) {
      this.totalScore = +this.gradingForm.file_submission_score + +newVal || 0
    }
  },
  created () {
    window.addEventListener('keydown', this.arrowListener)
  },
  destroyed () {
    window.removeEventListener('keydown', this.arrowListener)
    window.removeEventListener('message', this.receiveMessage)
  },
  mounted () {
    applyWarningsVisibility(this.user)
    this.routeStudentUserId = this.$route.params.studentUserId
    this.routeQuestionId = this.$route.params.questionId

    this.renderMathJax = +localStorage.renderMathJax === 1
    window.addEventListener('message', this.receiveMessage)
    h5pResizer()
    this.assignmentId = this.$route.params.assignmentId
    this.getAssignmentInfoForGrading()
    this.getFerpaMode()
    this.$forceUpdate()
  },
  methods: {
    addGlow,
    downloadSubmissionFile,
    downloadSolutionFile,
    getAcceptedFileTypes,
    getFullPdfUrlAtPage,
    setActiveComment (comment) {
      this.activeDicussionCommentId = comment.id
    },
    updateTranscriptInComment (caption, transcriptTiming, activeTranscriptTimingText) {
      const userDiscussion = this.discussionsByUserId.find(
        item => item.user_id === this.grading[this.currentStudentPage - 1].student.user_id
      )
      const commentIndex = userDiscussion?.comments.findIndex(
        item => item.id === this.activeDicussionCommentId
      )
      if (commentIndex > -1) {
        userDiscussion.comments[commentIndex].transcript[caption].text = activeTranscriptTimingText
        this.discussionCommentCacheKey++
      }
    },
    getScoreLabel () {
      if (this.isOpenEnded) {
        return 'Open-ended'
      }
      if (this.isDiscussIt) {
        return 'Discuss-it'
      }
      if (this.isForge) {
        return this.formattedForgeQuestionType
      }
      return ''
    },
    getForgeSubmissionHeader () {
      return `<h2 class="h7 mb-0">${this.formattedForgeQuestionType} Submission</span></h2>`
    },
    roundToDecimalSigFig,
    updateUploadCount () {
      this.grading[this.currentStudentPage - 1]['open_ended_submission']['upload_count'] = 0
    },
    async applyPendingScore () {
      this.gradingForm.question_submission_score = this.roundToDecimalSigFig(this.submittedWork.pending_score)
      this.gradingForm.submitted_work_pending_score = true
      const lastGraded = await this.submitGradingForm(false)
      if (lastGraded) {
        this.gradingForm.submitted_work_pending_score = false
        this.submittedWork.pending_score = null
        this.grading[this.currentStudentPage - 1]['last_graded'] = lastGraded
      }
    },
    async getSubmittedWorkWithPendingScore () {
      try {
        let current
        current = this.grading[this.currentStudentPage - 1]['auto_graded_submission']
        if (!current.question_id) {
          current = this.grading[this.currentStudentPage - 1]['open_ended_submission']
        }
        const { data } = await axios.get(`/api/submitted-work/assignments/${this.assignmentId}/questions/${current.question_id}/user/${current.user_id}/with-pending-score`)
        if (data.type === 'error') {
          this.$noty.error(data.message)
        } else {
          this.canSubmitWork = data.can_submit_work
          if (this.canSubmitWork) {
            this.submittedWork = data.submitted_work
          }
        }
      } catch (error) {
        this.$noty.error(error.message)
      }
    },
    viewInNewTab (url) {
      window.open(url, '_blank')
    },
    scannedImageMessage (grading) {
      try {
        if (grading &&
          grading.auto_graded_submission &&
          JSON.parse(grading.auto_graded_submission.submission) &&
          JSON.parse(grading.auto_graded_submission.submission).submitted_smiles &&
          JSON.parse(grading.auto_graded_submission.submission).image_smiles
        ) {
          const action = JSON.parse(grading.auto_graded_submission.submission).submitted_smiles ===
          JSON.parse(grading.auto_graded_submission.submission).image_smiles
            ? 'did not edit' : 'edited'
          return `The student ${action} the scanned image.`
        }
        return ''
      } catch (error) {
        return ''
      }
    },
    getStudentResponse (currentGrading) {
      if (currentGrading.student_response) {
        return currentGrading.student_response
      }
      if (JSON.parse(currentGrading['qti_json']) && ['submit_molecule', 'marker'].includes(JSON.parse(currentGrading['qti_json']).questionType)) {
        return currentGrading.auto_graded_submission.submission
      }
      return ''
    },
    async viewStructureImage (currentGrading) {
      this.structureImageTemporaryUrl = ''
      const questionId = currentGrading.auto_graded_submission.question_id
      const userId = currentGrading.auto_graded_submission.user_id
      try {
        const { data } = await axios.patch(`/api/math-pix/temporary-url`, {
          user_id: userId,
          assignment_id: this.assignmentId,
          question_id: questionId
        })
        if (data.type === 'error') {
          this.$noty.error(data.message)
          return
        }
        this.structureImageTemporaryUrl = data.temporary_url
        this.$bvModal.show('modal-structure-image')
      } catch (error) {
        this.$noty.error(error.message)
      }
    },
    setOriginalRubricWithMaxes (originalRubricWithMaxes) {
      this.originalRubricWithMaxes = originalRubricWithMaxes
    },
    updateOpenEndedSubmissionScore (rubricPointsBreakdown, points) {
      this.gradingForm.file_submission_score = points
      this.setRubricPointsBreakdown(rubricPointsBreakdown)
    },
    setRubricPointsBreakdown (rubricPointsBreakdown) {
      this.gradingForm.rubric_points_breakdown = rubricPointsBreakdown
    },
    async reloadRubricAndRubricPointsBreakdown () {
      try {
        const { data } = await axios.get(`/api/grading/${this.assignmentId}/${this.questionView}/${parseInt(this.sectionId)}/allStudents`)
        this.rubric = data.rubric
        this.$nextTick(() => {
          this.rubricPointsBreakdownIndex++
        })
      } catch (error) {
        this.$noty.error(error.message)
      }
    },
    async setCustomRubric (customRubric) {
      const questionId = this.grading[this.currentStudentPage - 1]['open_ended_submission']['question_id']
      try {
        const { data } = await axios.patch(`/api/assignments/${this.assignmentId}/questions/${questionId}/update-custom-rubric`,
          { custom_rubric: customRubric })
        if (data.type !== 'info') {
          this.$noty[data.type](data.message)
        }
        if (data.type !== 'error') {
          this.showRubricProperties = false
          await this.changePage()
          this.$nextTick(() => {
            this.rubricPointsBreakdownIndex++
          })
        }
      } catch (error) {
        this.$noty.error(error.message)
      }
    },
    openAssignmentGradebook () {
      window.open(`/instructors/assignments/${this.assignmentId}/information/gradebook`, '_blank')
    },
    openRegrader () {
      window.open(`/assignments/${this.assignmentId}/regrader`, '_blank')
    },
    visitQuestion () {
      window.open(`/assignments/${this.assignmentId}/questions/view/${this.questionView}`, '_blank')
    },
    updateRenderMathJax () {
      this.renderMathJax = !this.renderMathJax
      if (this.renderMathJax) {
        localStorage.renderMathJax = 1
        this.typesetMath()
      } else {
        localStorage.renderMathJax = 0
      }
    },
    getSubmissionSummaryTitle () {
      return '<h2 class="h7 mb-0">Submission History</h2>'
    },
    satisfiedRequirement (discussionCommentId) {
      if (this.discussItRequirementsInfo.satisfied_requirement_by_discussion_comment_id) {
        const discussionCommentInfo = this.discussItRequirementsInfo.satisfied_requirement_by_discussion_comment_id.find(value => +value.discussion_comment_id === +discussionCommentId)
        return discussionCommentInfo ? discussionCommentInfo.satisfied_requirement : false
      }
    },
    async getDiscussItRequirementInfo () {
      this.showDiscussIt = false
      const questionId = this.grading[this.currentStudentPage - 1]['open_ended_submission']['question_id']
      const userId = this.grading[this.currentStudentPage - 1]['open_ended_submission']['user_id']
      try {
        const { data } = await axios.get(`/api/discussion-comments/assignment/${this.assignmentId}/question/${questionId}/user/${userId}/satisfied`)
        if (data.type === 'error') {
          this.$noty.error(data.message)
        } else {
          this.discussItCompletionCriteria = data.completion_criteria
          this.discussItRequirementsInfo = data.satisfied_requirements
        }
      } catch (error) {
        this.$noty.error(error.message)
      }
      this.showDiscussIt = true
    },
    hasAtLeastOneComment () {
      for (let i = 0; i < this.discussions.length; i++) {
        const discussion = this.discussions[i]
        for (let j = 0; j < discussion.comments.length; j++) {
          if (discussion.comments[j]['created_by_user_id'] === this.grading[this.currentStudentPage - 1].student.user_id) {
            return true
          }
        }
      }
      return false
    },
    resetView () {
      this.gradeView = 'allStudents'
      this.processing = true
      this.getGrading()
    },
    showDiscussion (discussionId, userId) {
      this.activeDiscussion = this.discussions.find(value => value.id === discussionId)
      this.activeUserId = userId
      this.$nextTick(() => {
        this.$bvModal.show('modal-discussion')
      })
    },
    hasMaxScore () {
      return ((1 * this.grading[this.currentStudentPage - 1]['open_ended_submission']['question_submission_score'] || 0) +
          (1 * this.grading[this.currentStudentPage - 1]['open_ended_submission']['file_submission_score'] || 0)) ===
        this.grading[this.currentStudentPage - 1]['open_ended_submission']['points'] * 1
    },
    receiveMessage (event) {
      this.event = event
      let jsonObj = {}
      try {
        jsonObj = JSON.parse(event.data)
      } catch (e) {
        console.error('Why me???!?@?#')
      }
      if (event.data === 'loaded' || jsonObj.type === 'webwork.lifecycle.loaded') {
        switch (this.technology) {
          case ('webwork'):
            webworkOnLoadCssUpdates.elements.push({
              selector: 'input[name="submitAnswers"]',
              style: 'display:none'
            })
            event.source.postMessage(JSON.stringify(webworkOnLoadCssUpdates), event.origin)
            break
          case ('h5p'):
            h5pOnLoadCssUpdates.elements.push({
              selector: '.h5p-actions',
              style: 'display:none;'
            })
            event.source.postMessage(JSON.stringify(h5pOnLoadCssUpdates), event.origin)
            break
        }
      } else {
        try {
          console.log(event)
          console.log(event.data)
          let jsonObj = JSON.parse(event.data)
          console.log(jsonObj.solutions)
          if (jsonObj.solutions.length) {
            this.renderedWebworkSolution = '<h2 class="editable">Solution</h2>'
            for (let i = 0; i < jsonObj.solutions.length; i++) {
              this.renderedWebworkSolution += jsonObj.solutions[i]
            }
            this.solutions[0].solution_type = 'html'
            this.solutions[0].solution_html = this.renderedWebworkSolution
            console.log(this.solutions)
          }
          console.log(this.renderedWebworkSolution)
        } catch (error) {
          console.log(error.message)
        }
      }
    },
    reloadSubmissionOverrideScore () {
      this.grading[this.currentStudentPage - 1]['submission_score_override'] = this.overrideSubmissionScoreForm.score
    },
    initOverrideSubmissionScore (obj) {
      let score = 0
      if (this.gradingForm.question_submission_score) {
        score += this.gradingForm.question_submission_score
      }

      if (this.gradingForm.file_submission_score) {
        score += this.gradingForm.file_submission_score
      }
      this.originalScore = score
      const questionId = obj.open_ended_submission.question_id
      const questionNum = this.questionOptions.find(item => +item.value === +questionId).text
      this.questionTitle = `Question #${questionNum}`
      this.firstLast = obj.student.name
      this.activeSubmissionScore = obj
      this.overrideSubmissionScoreForm = new Form({
        assignment_id: this.assignmentId,
        question_id: questionId,
        student_user_id: obj.student.user_id,
        first_last: this.firstLast,
        question_title: this.questionTitle,
        score: this.grading[this.currentStudentPage - 1]['submission_score_override']
      })
      this.$bvModal.show('modal-override-submission-score')
    },
    applyLatePenalty () {
      if ((1 * this.grading[this.currentStudentPage - 1]['open_ended_submission']['file_submission_score'] || 0) > 0) {
        let appliedLatePenalty = Number(this.grading[this.currentStudentPage - 1]['open_ended_submission']['applied_late_penalty'].replace(/\D+/g, ''))
        if (appliedLatePenalty > 100 || appliedLatePenalty < 0) {
          this.$noty.error('The late penalty should be between 0 and 100.')
          return false
        }
        this.gradingForm.file_submission_score = this.gradingForm.file_submission_score * (100 - appliedLatePenalty) / 100
        this.grading[this.currentStudentPage - 1]['open_ended_submission']['file_submission_score'] = this.gradingForm.file_submission_score
        this.gradingForm.applied_late_penalty = appliedLatePenalty
        this.$noty.success('The late penalty has been applied. Be sure to submit the score to save it.')
      } else {
        this.$noty.info('Please first enter a score.')
      }
    },
    saveOpenEndedScore (openEndedScore) {
      this.gradingForm.file_submission_score = openEndedScore
      this.submitGradingForm(false,
        {
          scoreType: 'file_submission_score',
          score: this.gradingForm.file_submission_score
        }, true)
    },
    updateFullView () {
      this.fullView = !this.fullView
    },
    searchByStudent (input) {
      if (input.length < 1) {
        return []
      }
      let matches = this.students.filter(user => user.toLowerCase().includes(input.toLowerCase()))
      let items = []
      if (matches) {
        for (let i = 0; i < matches.length; i++) {
          items.push(matches[i])
        }
        items.sort()
      }
      return items
    },
    handleFixCKEditor () {
      fixCKEditor(this)
    },
    async getFerpaMode () {
      try {
        const { data } = await axios.get(`/api/scores/get-ferpa-mode`)
        if (data.type !== 'success') {
          this.$noty.error(data.message)
          return false
        }
        this.ferpaMode = Boolean(data.ferpa_mode)
      } catch (error) {
        this.$noty.error(error.message)
      }
    },
    async submitFerpaMode () {
      try {
        const { data } = await axios.patch(`/api/cookie/set-ferpa-mode/${+this.ferpaMode}`)
        if (data.type === 'success') {
          this.isLoading = true
          this.ferpaMode = !this.ferpaMode
          await this.getGrading(false)
          this.isLoading = false
        }
      } catch (error) {
        this.$noty.error(error.message)
      }
    },
    arrowListener (event) {
      let inTextArea = document.activeElement.id === 'text_comments'
      if (event.key === 'ArrowRight' &&
        this.currentStudentPage < this.numStudents &&
        !inTextArea) {
        this.currentStudentPage++
        this.changePage()
      }
      if (event.key === 'ArrowLeft' &&
        this.currentStudentPage > 1 &&
        !inTextArea) {
        this.currentStudentPage--
        this.changePage()
      }
    },
    onCKEditorNamespaceLoaded (CKEDITOR) {
      CKEDITOR.addCss('.cke_editable { font-size: 15px; }')
      fixCKEditor(this)
    },
    async removeCannedResponse (cannedResponseId) {
      try {
        const { data } = await this.cannedResponseForm.delete(`/api/canned-responses/${cannedResponseId}`)
        this.$noty[data.type](data.message)
        if (data.type !== 'error') {
          await this.getCannedResponses()
        }
      } catch (error) {
        this.$noty.error(error.message)
      }
    },
    async submitCannedResponseForm (bvModalEvt) {
      bvModalEvt.preventDefault()
      try {
        const { data } = await this.cannedResponseForm.post('/api/canned-responses')
        this.$noty[data.type](data.message)
        if (data.type !== 'error') {
          this.cannedResponseForm.canned_response = ''
          await this.getCannedResponses()
        }
      } catch (error) {
        if (!error.message.includes('status code 422')) {
          this.$noty.error(error.message)
        } else {
          this.$nextTick(() => fixInvalid())
          this.allFormErrors = this.cannedResponseForm.errors.flatten()
          this.$bvModal.show('modal-errors-canned-response')
        }
      }
    },
    async getCannedResponses () {
      try {
        const { data } = await axios.get('/api/canned-responses')
        if (data.type !== 'success') {
          this.$noty.error(data.message)
          return false
        }
        this.cannedResponseOptions = [{ text: 'Please choose a response', value: null }]
        for (let i = 0; i < data.canned_responses.length; i++) {
          let cannedResponse = data.canned_responses[i]
          let cannedResponseOption = { text: cannedResponse.canned_response, value: cannedResponse.id }
          this.cannedResponseOptions.push(cannedResponseOption)
        }
        this.cannedResponses = data.canned_responses
        return true
      } catch (error) {
        this.$noty.error(error.message)
        return false
      }
    },
    async openEditCannedResponsesModal () {
      this.cannedResponseForm.errors.clear()
      let success = await this.getCannedResponses()
      if (success) {
        this.$bvModal.show('modal-edit-canned-responses')
      }
    },
    async getFilesFromS3 () {
      try {
        let current = this.grading[this.currentStudentPage - 1]['open_ended_submission']
        if (!current.got_files) {
          const { data } = await axios.post(`/api/submission-files/get-files-from-s3/${this.assignmentId}/${current.question_id}/${current.user_id}`, { open_ended_submission_type: current.open_ended_submission_type })
          console.log('getting files')
          if (data.type === 'success') {
            let files = data.files
            this.grading[this.currentStudentPage - 1]['open_ended_submission'].file_feedback_url = files.file_feedback_url
            this.grading[this.currentStudentPage - 1]['open_ended_submission'].submission_url = files.submission_url
            this.grading[this.currentStudentPage - 1]['open_ended_submission'].submission_text = files.submission_text
            this.grading[this.currentStudentPage - 1]['open_ended_submission'].got_files = true
          } else {
            this.$noty.error(data.message)
          }
        }
        console.log(current)
      } catch (error) {
        this.$noty.error(`We could not retrieve the files for the student. ${error.message}`)
      }
    },
    setQuestionAndStudentByStudentName (jumpToStudent) {
      this.jumpToStudent = jumpToStudent
      for (let j = 0; j < this.grading.length; j++) {
        if (this.jumpToStudent === this.grading[j]['student']['name']) {
          this.currentStudentPage = j + 1
          this.$refs.searchStudent.value = ''
          this.changePage()
          return
        }
      }
      this.jumpToStudent = ''
    },
    handleCancel () {
      this.$bvModal.hide(`modal-upload-file`)
    },
    failedAudioFeedbackUpload (data) {
      this.$bvModal.hide('modal-upload-file')
      this.$noty.error('We were not able to perform the upload.  Please try again or contact us for assistance.')
      axios.post('/api/submission-audios/error', JSON.stringify(data))
    },
    submittedAudioFeedbackUpload (response) {
      let data = response.data
      this.audioFeedbackDataType = (data.type === 'success') ? 'success' : 'danger'
      this.audioFeedbackDataMessage = data.message
      this.showAudioFeedbackMessage = true
      setTimeout(() => {
        this.showAudioFeedbackMessage = false
      }, 3000)
      if (data.type === 'success') {
        this.grading[this.currentStudentPage - 1]['open_ended_submission'].file_feedback_url = data.file_feedback_url
        this.grading[this.currentStudentPage - 1]['open_ended_submission'].file_feedback_type = data.file_feedback_type
      }
      this.viewSubmission = false
      this.$refs.recorder.removeRecord()
      this.$bvModal.hide('modal-upload-file')
    },
    capitalize (word) {
      return word.charAt(0).toUpperCase() + word.slice(1)
    },
    getGraderFeedbackTitle () {
      let grader = this.grading[this.currentStudentPage - 1]['open_ended_submission'].grader_name
        ? 'by ' + this.grading[this.currentStudentPage - 1]['open_ended_submission'].grader_name
        : ''
      return `<h2 class="h7 mb-0">Grader Feedback ${grader}</h2>`
    },
    openInNewTab (url) {
      console.log(url)
      window.open(url, '_blank')
    },
    async getAssignmentInfoForGrading () {
      try {
        const { data } = await axios.get(`/api/assignments/${this.assignmentId}/get-info-for-grading`)
        if (data.type === 'error') {
          this.$noty.error(data.message)
          return false
        }

        let assignment = data.assignment
        this.scoringType = assignment.scoring_type
        this.questionOptions = data.questions
        if (!data.questions.length) {
          this.isLoading = false
          this.showNoAutoGradedOrOpenSubmissionsExistAlert = true
          return false
        }
        this.questionView = this.questionOptions[0].value
        let sections = data.sections
        this.hasMultipleSections = sections.length > 1
        if (this.hasMultipleSections) {
          for (let i = 0; i < sections.length; i++) {
            let section = sections[i]
            this.sections.push({ text: section.name, value: section.id })
          }
        }

        this.title = `Open Grader For ${assignment.name}`
        this.latePolicy = assignment.late_policy
        this.lateDeductionApplicationPeriod = assignment.late_deduction_application_period
        this.lateDeductionPercent = assignment.late_deduction_percent
        await this.getGrading(false)
        await this.getCannedResponses()
      } catch (error) {
        this.title = 'Grade Submissions'
      }
    },
    async toggleView () {
      if (this.viewSubmission) return
      if (this.grading.length > 0 && (this.grading[this.currentStudentPage - 1]['open_ended_submission']['file_feedback_url'] === null)) {
        this.$noty.info('You have not uploaded a feedback file.')
        return false
      }
      this.viewSubmission = !this.viewSubmission
    },
    async submitGradingForm (next, prepopulatedScore = {}, justShowErrorMessage = false) {
      this.gradingForm.special_score = null
      if (prepopulatedScore.scoreType) {
        this.gradingForm.rubric_points_breakdown = prepopulatedScore.scoreType === 'file_submission_score' && this.rubric
        this.gradingForm.original_rubric_with_maxes = this.originalRubricWithMaxes
        this.gradingForm.special_score = prepopulatedScore.specialScore
        this.gradingForm[prepopulatedScore.scoreType] = prepopulatedScore.score
      }
      try {
        if (this.textFeedbackMode === 'canned_response' && this.cannedResponse === null) {
          this.$noty.error('You need to choose a response.')
          return false
        }
        this.gradingForm.text_feedback_editor = (this.textFeedbackMode === 'rich_text') ? 'rich' : 'plain'
        this.gradingForm.textFeedback = this.getTextFeedback(this.textFeedbackMode)
        if (this.gradingForm.textFeedback === false) {
          this.$noty.error('That is not a valid feedback mode.')
          return false
        }

        this.gradingForm.assignment_id = this.assignmentId
        this.gradingForm.question_id = this.grading[this.currentStudentPage - 1]['open_ended_submission']['question_id']
        this.gradingForm.user_id = this.grading[this.currentStudentPage - 1]['open_ended_submission']['user_id']
        const { data } = await this.gradingForm.post('/api/grading')
        if (data.manual_grading_error) {
          this.$bvModal.show('modal-manual-grading-error')
          return false
        }
        if (justShowErrorMessage) {
          if (data.type === 'error') {
            this.$noty.error(data.message)
          }
        } else {
          this.$noty[data.type](data.message)
        }
        if (data.type === 'success') {
          if ((this.isOpenEnded || this.isDiscussIt) && this.grading[this.currentStudentPage - 1]['open_ended_submission']) {
            this.grading[this.currentStudentPage - 1]['open_ended_submission']['file_submission_score'] = this.gradingForm.file_submission_score
            this.grading[this.currentStudentPage - 1]['open_ended_submission']['grader_name'] = data.grader_name
            this.grading[this.currentStudentPage - 1]['open_ended_submission']['text_feedback_editor'] = this.gradingForm.text_feedback_editor
            this.grading[this.currentStudentPage - 1]['open_ended_submission']['text_feedback'] = this.gradingForm.textFeedback
            this.grading[this.currentStudentPage - 1]['last_graded'] = data.last_graded
          }
          if (this.isAutoGraded && this.grading[this.currentStudentPage - 1]['auto_graded_submission']) {
            this.grading[this.currentStudentPage - 1]['auto_graded_submission']['score'] = this.gradingForm.question_submission_score
          }
          if (next && this.currentStudentPage < this.numStudents) {
            this.currentStudentPage++
            await this.changePage()
          } else {
            return data.last_graded
          }
        }
      } catch (error) {
        console.log(this.gradingForm.errors.errors)
        if (!error.message.includes('status code 422')) {
          this.$noty.error(error.message)
        } else {
          if (this.gradingForm.errors.errors.question_submission_score) {
            this.questionSubmissionScoreErrorMessage = this.gradingForm.errors.errors.question_submission_score[0]
          }
          if (this.gradingForm.errors.errors.file_submission_score) {
            this.fileSubmissionScoreErrorMessage = this.gradingForm.errors.errors.file_submission_score[0]
          }
          this.allFormErrors = this.gradingForm.errors.flatten()
          this.$bvModal.show('modal-errors-grading-form')
        }
        return false
      }
    },
    openUploadFileModal () {
      this.fileFeedbackForm.errors.clear('fileFeedback')
      let assignmentId = parseInt(this.assignmentId)
      let questionId = parseInt(this.grading[this.currentStudentPage - 1]['open_ended_submission']['question_id'])
      let studentUserId = parseInt(this.grading[this.currentStudentPage - 1]['open_ended_submission']['user_id'])
      this.audioFeedbackUploadUrl = `/api/submission-audios/audio-feedback/${studentUserId}/${assignmentId}/${questionId}`
      this.$bvModal.show(`modal-upload-file`)
    },
    async handleOk (bvModalEvt) {
      bvModalEvt.preventDefault()
      try {
        if (this.uploading) {
          this.$noty.info('Please be patient while the file is uploading.')
          return false
        }
        this.fileFeedbackForm.errors.set('fileFeedback', null)
        this.uploading = true
        // https://stackoverflow.com/questions/49328956/file-upload-with-vue-and-laravel
        let formData = new FormData()
        formData.append('fileFeedback', this.fileFeedbackForm.fileFeedback)
        formData.append('assignmentId', this.assignmentId)
        formData.append('questionId', this.grading[this.currentStudentPage - 1]['open_ended_submission']['question_id'])
        formData.append('userId', this.grading[this.currentStudentPage - 1]['open_ended_submission']['user_id'])
        formData.append('_method', 'put') // add this
        const { data } = await axios.post('/api/submission-files/file-feedback', formData)
        console.log(data)
        if (data.type === 'error') {
          this.fileFeedbackForm.errors.set('fileFeedback', data.message)
        } else {
          this.$noty.success(data.message)
          this.grading[this.currentStudentPage - 1]['open_ended_submission']['file_feedback_url'] = data.file_feedback_url
          this.grading[this.currentStudentPage - 1]['open_ended_submission']['file_feedback_type'] = data.file_feedback_type
          this.$bvModal.hide('modal-upload-file')
        }
      } catch (error) {
        if (error.message.includes('status code 413')) {
          error.message = 'The maximum size allowed is 10MB.'
        }
        this.$noty.error(error.message)
      }
      this.uploading = false
    },
    getTextFeedback (textFeedbackMode) {
      switch (textFeedbackMode) {
        case ('rich_text'):
          return this.richTextFeedback
        case ('plain_text'):
          return this.plainTextFeedback
        case ('canned_response'):

          for (let i = 0; i < this.cannedResponseOptions.length; i++) {
            console.log(this.cannedResponseOptions[i].value)
            console.log(this.cannedResponse)
            if (this.cannedResponseOptions[i].value === this.cannedResponse) {
              return this.cannedResponseOptions[i].text
            }
          }
          break
        default:
          return false
      }
    },
    setTextFeedback (textFeedback) {
      this.richTextFeedback = ''
      this.plainTextFeedback = ''
      if (textFeedback) {
        this.textFeedbackMode = 'plain_text'
        switch (this.grading[this.currentStudentPage - 1]['open_ended_submission']['text_feedback_editor']) {
          case ('rich'):
            this.richTextFeedback = textFeedback
            this.textFeedbackMode = 'rich_text'
            break
          case ('plain'):
            this.plainTextFeedback = textFeedback
            this.textFeedbackMode = 'plain_text'
            break
          default:
            this.plainTextFeedback = textFeedback
            this.textFeedbackMode = 'plain_text'
            break
        }
      }
    },
    async getRubricsByQuestionId (questionId) {
      try {
        const { data } = await axios.get(`/api/assignments/${this.assignmentId}/questions/${questionId}/rubric-categories`)
        this.isLoading = false
        if (data.type === 'error') {
          this.$noty.error(data.message)
          return false
        }
        this.rubricCategories = data.rubric_categories
        this.rubricScale = data.rubric_scale
        this.$forceUpdate()
      } catch (error) {
        this.$noty.error(error.message)
      }
    },
    async changePage () {
      this.submittedWork = {}
      this.canSubmitWork = false
      if (!this.grading.length) {
        this.showNoAutoGradedOrOpenSubmissionsExistAlert = true
        return false
      }
      this.renderedWebworkSolution = ''
      this.submissionArray = []
      if (this.technology === 'webwork') {
        await this.getTechnologySrcDoc()
      }

      this.questionHeader = this.isAlgorithmic
        ? `<h2 class="h7 mb-0">Question (algorithmic)</span></h2>`
        : '<h2 class="h7 mb-0">Question</span></h2>'
      this.gradeViews = [
        { text: 'All Students', value: 'allStudents' },
        { text: 'Ungraded Open-Ended Submissions', value: 'ungradedOpenEndedSubmissions' },
        { text: 'Graded Open-Ended Submissions', value: 'gradedOpenEndedSubmissions' }
      ]
      if (this.isDiscussIt) {
        this.questionHeader = `<h2 class="h7 mb-0">Discussion Comments</span></h2>`
        this.gradeViews = [
          { text: 'All Students', value: 'allStudents' },
          { text: 'Ungraded Discussions', value: 'ungradedDiscussions' },
          { text: 'Graded Discussions', value: 'gradedDiscussions' }
        ]
      }
      this.retrievedFromS3 = false
      this.showAudioFeedbackMessage = false
      this.cardBorderColor = ''
      if (this.isOpenEnded) {
        this.cardBorderColor = this.hasMaxScore() || (!this.grading[this.currentStudentPage - 1]['open_ended_submission']['date_submitted'] || this.grading[this.currentStudentPage - 1]['submission_status'] === 'gradedOpenEndedSubmissions')
          ? 'green'
          : 'red'
      }
      this.noSubmission = this.isDiscussIt
        ? !this.hasAtLeastOneComment()
        : !this.grading[this.currentStudentPage - 1]['auto_graded_submission'] &&
        this.grading[this.currentStudentPage - 1]['open_ended_submission']['submission'] === null

      let textFeedback = this.grading[this.currentStudentPage - 1]['open_ended_submission']['text_feedback']
      this.gradingForm.file_submission_score = this.grading[this.currentStudentPage - 1]['open_ended_submission']['file_submission_score'] === 'N/A'
        ? null
        : 1 * this.grading[this.currentStudentPage - 1]['open_ended_submission']['file_submission_score']
      this.gradingForm.question_submission_score = !this.grading[this.currentStudentPage - 1]['auto_graded_submission']
        ? null
        : 1 * this.grading[this.currentStudentPage - 1]['auto_graded_submission']['score']

      this.setTextFeedback(textFeedback)
      this.gradingForm.textFeedback = this.grading[this.currentStudentPage - 1]['open_ended_submission']['text_feedback']
      console.log(this.grading[this.currentStudentPage - 1]['open_ended_submission'])

      this.openEndedType = this.grading[this.currentStudentPage - 1]['open_ended_submission'].open_ended_submission_type
      await this.getSubmittedWorkWithPendingScore()
      await this.getFilesFromS3()
      if (this.isDiscussIt) {
        await this.getDiscussItRequirementInfo()
      }
      let submission = this.grading[this.currentStudentPage - 1]['open_ended_submission'].submission
      if (submission !== null && submission.split('.').pop() === 'pdf') {
        this.isOpenEndedFileSubmission = true
      } else {
        this.isOpenEndedFileSubmission = (this.openEndedType === 'file')
        this.isOpenEndedAudioSubmission = (this.openEndedType === 'audio')
        this.isOpenEndedTextSubmission = (this.openEndedType === 'text')
      }
      this.retrievedFromS3 = true
      this.totalScore =
        (1 * this.grading[this.currentStudentPage - 1]['open_ended_submission']['question_submission_score'] || 0) +
        (1 * this.grading[this.currentStudentPage - 1]['open_ended_submission']['file_submission_score'] || 0)
      await this.getStudentTimeLimitStatus()
    },
    async getStudentTimeLimitStatus () {
      this.studentTimeLimitStatus = {}
      try {
        const studentUserId = this.grading[this.currentStudentPage - 1].student.user_id
        const { data } = await axios.get(`/api/assignments/${this.assignmentId}/time-limit/${studentUserId}/status`)
        if (data.type === 'error') {
          // Not every assignment has a time limit - a missing/blank response
          // here is the normal case, not something to surface as an error.
          return
        }
        this.studentTimeLimitStatus = data
      } catch (error) {
        this.$noty.error(error.message)
      }
    },
    formattedCountdownText (props) {
      const parts = []
      if (props.hours > 0) parts.push(`${props.hours}h`)
      if (props.minutes > 0) parts.push(`${props.minutes}m`)
      parts.push(`${props.seconds}s`)
      return parts.join(' ')
    },
    resetTimerModalInputs () {
      this.minutesToAddForStudent = null
      this.newTimeLimitForStudent = ''
    },
    looksLikeValidDuration (value) {
      if (!value) {
        return false
      }
      const trimmed = value.trim()
      // ISO 8601 duration, e.g. PT1H30M
      if (/^P(\d+Y)?(\d+M)?(\d+D)?(T(\d+H)?(\d+M)?(\d+S)?)?$/i.test(trimmed) && trimmed.toUpperCase() !== 'P') {
        return true
      }
      // Human phrases like "2 minutes", "1 hour, 30 minutes", "90 seconds"
      return /^\d+\s*(second|minute|hour|day|week)s?(\s*,?\s*\d+\s*(second|minute|hour|day|week)s?)*$/i.test(trimmed)
    },
    showTimerConfirm (message, action) {
      this.confirmTimerModalMessage = message
      this.pendingTimerConfirmAction = action
      this.$bvModal.show('modal-confirm-timer-action')
    },
    async runPendingTimerConfirmAction () {
      if (this.pendingTimerConfirmAction) {
        await this.pendingTimerConfirmAction()
        this.pendingTimerConfirmAction = null
      }
    },
    async addTimeForStudent () {
      const alreadyExpired = this.studentTimeLimitStatus.started_at && this.studentTimeLimitStatus.seconds_left <= 0
      if (alreadyExpired) {
        this.showTimerConfirm(
          `This student's time limit has already expired. Adding ${this.minutesToAddForStudent} minute(s) will give them that much time starting now. Continue?`,
          () => this.performAddTimeForStudent()
        )
        return
      }
      await this.performAddTimeForStudent()
    },
    async performAddTimeForStudent () {
      const studentUserId = this.grading[this.currentStudentPage - 1].student.user_id
      this.addingTimeForStudent = true
      try {
        const { data } = await axios.post(
          `/api/assignments/${this.assignmentId}/time-limit/${studentUserId}/add-time`,
          { minutes: this.minutesToAddForStudent }
        )
        if (data.type === 'error') {
          this.$noty.error(data.message)
          return
        }
        await this.getStudentTimeLimitStatus()
        this.$noty.success(
          data.extended_past_due
            ? `Time added. Note: this student's time now extends past the assignment due date.`
            : 'Time added.'
        )
      } catch (error) {
        this.$noty.error(error.message)
      } finally {
        this.addingTimeForStudent = false
      }
    },
    async setTimeForStudent () {
      if (!this.newTimeLimitForStudent || !this.looksLikeValidDuration(this.newTimeLimitForStudent)) {
        return
      }
      // We can't reliably compute the resulting expiry client-side (it
      // depends on when they started, which the server tracks) - the server
      // reports extended_past_due in the response after the fact, so this is
      // a plain confirmation of the action rather than a specific claim about
      // whether it will exceed due.
      this.showTimerConfirm(
        'This will set a new total duration for this student, replacing their current clock. Continue?',
        () => this.performSetTimeForStudent()
      )
    },
    async performSetTimeForStudent () {
      const studentUserId = this.grading[this.currentStudentPage - 1].student.user_id
      this.settingTimeForStudent = true
      try {
        const { data } = await axios.patch(
          `/api/assignments/${this.assignmentId}/time-limit/${studentUserId}`,
          { time_limit: this.newTimeLimitForStudent }
        )
        if (data.type === 'error') {
          this.$noty.error(data.message)
          return
        }
        await this.getStudentTimeLimitStatus()
        this.newTimeLimitForStudent = ''
        this.$bvModal.hide('modal-manage-student-timer')
        this.$noty.success(
          data.extended_past_due
            ? `Time set. Note: this student's time now extends past the assignment due date.`
            : 'Time set.'
        )
      } catch (error) {
        this.$noty.error(error.message)
      } finally {
        this.settingTimeForStudent = false
      }
    },
    async resetStudentTimer () {
      this.showTimerConfirm(
        'This will fully reset this student\'s timer. They will need to click Start Assignment again for a fresh duration. Continue?',
        () => this.performResetStudentTimer()
      )
    },
    async performResetStudentTimer () {
      const studentUserId = this.grading[this.currentStudentPage - 1].student.user_id
      this.resettingStudentTimer = true
      try {
        const { data } = await axios.delete(`/api/assignments/${this.assignmentId}/time-limit/${studentUserId}/reset`)
        if (data.type === 'error') {
          this.$noty.error(data.message)
          return
        }
        await this.getStudentTimeLimitStatus()
        this.$noty.success('Timer reset for this student.')
      } catch (error) {
        this.$noty.error(error.message)
      } finally {
        this.resettingStudentTimer = false
      }
    },
    retryUntilEventNotNull (callback, interval = 100, maxAttempts = 50) {
      let attempts = 0
      const intervalId = setInterval(() => {
        attempts++
        if (this.event !== null || attempts >= maxAttempts) {
          clearInterval(intervalId)
          if (this.event !== null) {
            callback()
          } else {
            console.error('Max attempts reached. Event is still null.')
          }
        }
      }, interval)
    },
    async getTechnologySrcDoc () {
      try {
        const questionId = this.grading[this.currentStudentPage - 1]['auto_graded_submission']['question_id']
        if (questionId) {
          const { data } = await axios.post(`/api/webwork/src-doc/assignment/${this.assignmentId}/question/${questionId}`, {
            url: this.grading[this.currentStudentPage - 1]['technology_iframe'],
            table: 'submissions',
            student_user_id: this.grading[this.currentStudentPage - 1]['student']['user_id']
          })
          if (data.type === 'error') {
            this.$noty.error(data.message)
            return false
          }
          this.submissionArray = data.submission_array
          this.retryUntilEventNotNull(() => {
            console.log('finally adding glow')
            this.addGlow(this.event, this.submissionArray, this.technology)
            this.addGlowTwiceMore()
          })
          if (this.renderMathJax) {
            this.typesetMath()
          }
        }
      } catch (error) {
        this.$noty.error(error.message)
      }
    },
    addGlowTwiceMore () {
      for (let i = 0; i < 10; i++) {
        console.log('adding again')
        setTimeout(() => {
          this.addGlow(this.event, this.submissionArray, this.technology)
        }, 50)
      }
    },
    async getTemporaryUrl (file, currentQuestionPage, currentStudentPage) {
      if (this.grading[currentStudentPage - 1][file] && !this.grading[currentStudentPage - 1][`${file}_url`]) {
        try {
          const { data } = await axios.post('/api/submission-files/get-temporary-url-from-request',
            {
              'assignment_id': this.assignmentId,
              'file': this.grading[currentStudentPage - 1][file]
            })
          if (data.type === 'error') {
            this.$noty.error(data.message)
            return false
          }
          this.grading[currentStudentPage - 1][`${file}_url`] = data.temporary_url
        } catch (error) {
          this.$noty.error(error.message)
        }
      }
    },
    submissionUrlExists (currentStudentPage) {
      return (this.grading[currentStudentPage - 1]['open_ended_submission']['submission_url'] !== null)
    },
    setQuestionAndStudentByQuestionIdAndStudentUserId (questionId, studentUserId) {
      for (let i = 0; i < this.grading.length; i++) {
        if (parseInt(studentUserId) === parseInt(this.grading[i]['student']['user_id'])) {
          this.currentStudentPage = i + 1
          return
        }
      }
    },
    async getGrading (showMessage = true) {
      if (this.routeQuestionId && this.routeStudentUserId) {
        this.questionView = this.$route.params.questionId
        this.gradeView = 'allStudents'
        this.sectionId = 0
      }
      let gradeView
      gradeView = this.gradeView
      if (['gradedDiscussions', 'ungradedDiscussions'].includes(this.gradeView)) {
        gradeView = 'allStudents'
      }
      try {
        const { data } = await axios.get(`/api/grading/${this.assignmentId}/${this.questionView}/${parseInt(this.sectionId)}/${gradeView}`)
        if (data.type === 'error') {
          this.$noty.error(data.message)
          this.isLoading = false
          this.processing = false
          return false
        }
        this.rubric = data.rubric
        this.showNoAutoGradedOrOpenSubmissionsExistAlert = !(data.grading.length > 0)
        if (this.showNoAutoGradedOrOpenSubmissionsExistAlert) {
          this.isLoading = false
          this.processing = false
          return false
        }
        for (let i = 0; i < data.grading.length; i++) {
          let grading = data.grading[i]
          if (grading['open_ended_submission']['late_penalty_percent']) {
            let latePenaltyPercent = grading['open_ended_submission']['applied_late_penalty']
              ? grading['open_ended_submission']['applied_late_penalty']
              : grading['open_ended_submission']['late_penalty_percent']
            data.grading[i]['open_ended_submission']['applied_late_penalty'] = `${latePenaltyPercent}%`
          }
        }
        this.grading = data.grading
        console.log(this.grading)
        this.technology = data.technology
        this.isDiscussIt = data.discuss_it
        this.isForge = data.forge_question_type !== null
        if (this.isForge) {
          this.formattedForgeQuestionType = data.forge_question_type === 'forge' ? 'Final' : 'Draft'
        }
        this.qtiAnswerJson = data.qti_answer_json
        if (this.isDiscussIt) {
          await this.getDiscussItRequirementInfo()
          switch (this.gradeView) {
            case ('ungradedDiscussions'):
              const studentsWhoSubmittedComments = []
              for (let i = 0; i < this.discussionsByUserId.length; i++) {
                const discussion = this.discussionsByUserId[i]
                if (discussion.comments) {
                  studentsWhoSubmittedComments.push(discussion.user_id)
                }
              }
              console.log(studentsWhoSubmittedComments)
              this.grading = this.grading.filter(item => !item.last_graded && studentsWhoSubmittedComments.includes(item.student.user_id))
              if (!this.grading.length) {
                this.showNoAutoGradedOrOpenSubmissionsExistAlert = true
                this.isLoading = false
                this.processing = false
                return false
              }
              break
            case ('gradedDiscussions'):
              this.grading = this.grading.filter(item => item.last_graded)
              break
          }
        }
        this.discussions = data.discussions
        this.discussionsByUserId = data.discussions_by_user_id
        this.isAlgorithmic = data.algorithmic
        this.isOpenEnded = data.is_open_ended
        this.isAutoGraded = data.is_auto_graded
        this.gradersCanSeeStudentNames = data.graders_can_see_student_names
        this.students = []
        this.numStudents = Object.keys(this.grading).length
        this.jumpToStudentsByNumber = [{ text: '--', value: '--' }]
        for (let i = 0; i < this.numStudents; i++) {
          this.students.push(this.grading[i]['student'].name)
          this.jumpToStudentsByNumber.push({ text: i + 1, value: i + 1 })
        }

        this.currentStudentPage = 1

        // loop through questions, inner loop through students, if match, then set question and student)

        if (this.routeQuestionId && this.routeStudentUserId) {
          this.setQuestionAndStudentByQuestionIdAndStudentUserId(this.routeQuestionId, this.routeStudentUserId)
          this.routeQuestionId = null
          this.routeStudentUserId = null
        }
        let questionOptions = JSON.parse(JSON.stringify(this.questionOptions))
        this.solutions = [questionOptions.find(question => +question.value === +this.questionView).solution]
        await this.changePage()

        if (showMessage) {
          this.$noty.info(data.message)
        }
      } catch (error) {
        this.$noty.error(error.message)
      }
      this.isLoading = false
      this.processing = false
    }
  }
}
</script>
<style>
div.ar-icon svg {
  vertical-align: top !important;
}
</style>
