<template>
  <div>
    <AllFormErrors :all-form-errors="allFormErrors" :modal-id="'modal-errors-canned-response'"/>
    <AllFormErrors :all-form-errors="allFormErrors" :modal-id="'modal-errors-grading-form'"/>
    <RubricPropertiesModal :key="`grader-rubric-properties-modal-${+showRubricProperties}`"
                           :show-rubric-properties="showRubricProperties"
                           :modal-title="rubric ? 'Edit Rubric' : 'Add Rubric'"
                           :is-edit="Boolean(rubric)"
                           :rubric-info="{ rubric: rubric }"
                           :is-template="false"
                           :question-points="currentQuestionPoints"
                           :assignment-id="+assignmentId"
                           :question-id="+questionView"
                           :rubric-permissions="rubricEditPermission.settings"
                           @setCustomRubric="setCustomRubric"
                           @dynamicRubricSaved="dynamicRubricSaved"
                           @hideRubricProperties="showRubricProperties = false"
    />
    <b-modal id="modal-static-needs-review-mismatch"
             title="The Criteria Don't Add Up to the Score"
             no-close-on-backdrop
    >
      <p v-if="staticNeedsReviewMismatch">
        This student's criteria add up to <strong>{{ formatPoints(staticNeedsReviewMismatch.expected) }}</strong><span
          v-if="staticNeedsReviewMismatch.penalty"
        > (after the {{ formatPoints(staticNeedsReviewMismatch.penalty) }}% late penalty)</span>, but their open-ended
        score is <strong>{{ formatPoints(staticNeedsReviewMismatch.score) }}</strong>. Saving marks them as reviewed.
      </p>
      <template #modal-footer>
        <b-button size="sm" @click="$bvModal.hide('modal-static-needs-review-mismatch')">
          Cancel
        </b-button>
        <b-button v-if="staticNeedsReviewMismatch"
                  size="sm"
                  variant="outline-primary"
                  @click="resolveStaticMismatch(false)"
        >
          Keep {{ formatPoints(staticNeedsReviewMismatch.score) }}
        </b-button>
        <b-button v-if="staticNeedsReviewMismatch"
                  size="sm"
                  variant="primary"
                  @click="resolveStaticMismatch(true)"
        >
          Use {{ formatPoints(staticNeedsReviewMismatch.expected) }}
        </b-button>
      </template>
    </b-modal>
    <b-modal id="modal-rubric-updated-elsewhere"
             title="Rubric Updated"
             no-close-on-backdrop
             no-close-on-esc
             hide-header-close
    >
      <p>
        <span v-if="rubricUpdatedElsewhere.name">{{ rubricUpdatedElsewhere.name }} changed</span>
        <span v-else>Someone changed</span>
        the rubric for this question<span v-if="rubricUpdatedElsewhere.time"> at {{ rubricUpdatedElsewhere.time }}</span>.
        Students who were already graded have been rescored.
      </p>
      <p>Please reload to keep grading with the new rubric. You'll stay on the same student.</p>
      <p v-if="rubricUpdatedElsewhere.unsavedChanges" class="font-weight-bold">
        The changes you haven't saved for the current student will be discarded, since they were made with the old
        rubric.
      </p>
      <template #modal-footer>
        <b-button size="sm"
                  variant="primary"
                  :disabled="reloadingAfterRubricUpdate"
                  @click="reloadAfterRubricUpdatedElsewhere()"
        >
          Reload
        </b-button>
      </template>
    </b-modal>
    <b-modal id="modal-dynamic-rubric-history"
             title="Rubric History"
             size="lg"
             ok-only
             ok-title="Close"
             ok-variant="secondary"
             button-size="sm"
    >
      <div v-if="rubricHistoryLoading" class="text-center">
        <b-spinner small type="grow"/>
        Loading...
      </div>
      <p v-else-if="!rubricHistory.length">
        This rubric hasn't been changed since it became a dynamic rubric for this assignment.
      </p>
      <div v-else>
        <b-card v-for="entry in rubricHistory"
                :key="`rubric-history-${entry.id}`"
                class="mb-2"
        >
          <div class="font-weight-bold">
            {{ entry.changed_at }} &middot; {{ entry.name }}
          </div>
          <ul class="mb-1">
            <li v-for="(change, changeIndex) in rubricHistoryChanges(entry)"
                :key="`rubric-history-${entry.id}-${changeIndex}`"
            >
              {{ change }}
            </li>
          </ul>
          <div class="small text-muted">
            {{ entry.students_rescored }} student<span v-if="entry.students_rescored !== 1">s</span>
            {{ ['convert', 'convert_to_static', 'delete_override_clear', 'switch_rubric_clear'].includes(entry.action) ? 'cleared for regrading' : 'rescored' }}
          </div>
        </b-card>
      </div>
    </b-modal>

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
             title="No Open-Ended Submission"
             hide-footer
    >
      <p>
        The student has no open-ended submission so there's nothing to score.
      </p>
      <p class="mb-0 text-muted small">
        If you meant to grade this question without student uploads,
        <a :href="`/assignments/${this.assignmentId}/questions/view/${this.questionView}`" target="_blank">visit
          the question</a>
        and change its Open-Ended Submission Type to one of the manual options.
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
    <b-modal id="modal-grader-file-comment"
             :title="activeFileComment.original_filename || 'File'"
             size="xl"
             @hidden="activeFileComment = {}"
    >
      <VuePdfEmbed v-if="activeFileComment.file_kind === 'pdf' && activeFileComment.file_url"
                   annotation-layer
                   text-layer
                   :source="activeFileComment.file_url"
      />
      <img v-if="activeFileComment.file_kind === 'image' && activeFileComment.file_url"
           :src="activeFileComment.file_url"
           :alt="activeFileComment.original_filename"
           class="d-block mx-auto"
           style="max-width: 100%"
      >
      <template #modal-footer>
        <a v-if="activeFileComment.download_url"
           :href="activeFileComment.download_url"
           class="btn btn-sm btn-outline-primary mr-auto"
        ><b-icon icon="download" aria-hidden="true"/> Download</a>
        <b-button size="sm" variant="primary" @click="$bvModal.hide('modal-grader-file-comment')">
          OK
        </b-button>
      </template>
    </b-modal>
    <b-modal id="modal-timestamped-comment"
             :title="`Comment by ${timestampedComment.name} from ${timestampedComment.time}`"
             size="lg"
             @hidden="timestampedComment.src = ''"
    >
      <iframe v-if="timestampedComment.src"
              :key="timestampedComment.src"
              v-resize="{ log: false }"
              :src="timestampedComment.src"
              width="100%"
              frameborder="0"
              allowfullscreen=""
      />
      <template #modal-footer>
        <b-button size="sm"
                  variant="primary"
                  @click="$bvModal.hide('modal-timestamped-comment')"
        >
          OK
        </b-button>
      </template>
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
        <b-badge v-if="comment.assignment_id && +comment.assignment_id !== +assignmentId && comment.assignment_name"
                 variant="light"
                 class="border"
        >
          From {{ comment.assignment_name }}
        </b-badge>
        <div v-if="comment.timestamps && comment.timestamps.length" class="mt-1 mb-1">
          <b-button v-for="timestamp in comment.timestamps"
                    :key="`timestamp-${timestamp.id}`"
                    size="sm"
                    variant="outline-secondary"
                    class="mr-1 py-0 font-weight-normal"
                    :aria-label="`Play ${timestamp.referenced_created_by_name}'s comment from ${formatSeconds(timestamp.seconds)}`"
                    @click="openTimestampedComment(timestamp)"
          >
            <b-icon icon="play-fill" aria-hidden="true"/>
            {{ timestamp.referenced_created_by_name }} {{ formatSeconds(timestamp.seconds) }}
          </b-button>
        </div>
        <span v-if="comment.text" v-html="comment.text"/>
        <div v-if="comment.attachments && comment.attachments.length" class="mt-1 mb-2">
          <div v-for="attachment in comment.attachments" :key="`attachment-${attachment.id}`" class="mb-1">
            <img v-if="attachment.file_kind === 'image' && attachment.file_url"
                 :src="attachment.file_url"
                 :alt="`Image attached by the student: ${attachment.original_filename}`"
                 class="d-block mb-1"
                 style="max-width: 100%; max-height: 400px; border-radius: 4px; cursor: pointer"
                 @click="viewFileComment(attachment)"
            >
            <b-icon icon="paperclip" aria-hidden="true"/>
            <!-- PDFs and images open in a window with a Download button; Office files download -->
            <a v-if="['pdf', 'image'].includes(attachment.file_kind)"
               href=""
               @click.prevent="viewFileComment(attachment)"
            >{{ attachment.original_filename }}</a>
            <a v-else :href="attachment.download_url">{{ attachment.original_filename }}</a>
          </div>
        </div>
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
              <!-- loading is shown where the grading panels go (grading-loading-placeholder) -->
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
            <div v-if="showStudentNavigation && !splitView" class="text-center mb-2">
              <b-button-group size="sm" aria-label="Move between students">
                <b-button variant="outline-primary"
                          :disabled="previousUngradedStudentPage === null"
                          @click="goToStudentPage(previousUngradedStudentPage)"
                >
                  Previous Ungraded
                </b-button>
                <b-button variant="outline-primary"
                          :disabled="currentStudentPage <= 1"
                          @click="goToStudentPage(currentStudentPage - 1)"
                >
                  Previous
                </b-button>
                <b-button variant="outline-primary"
                          :disabled="currentStudentPage >= numStudents"
                          @click="goToStudentPage(currentStudentPage + 1)"
                >
                  Next
                </b-button>
                <b-button variant="outline-primary"
                          :disabled="nextUngradedStudentPage === null"
                          @click="goToStudentPage(nextUngradedStudentPage)"
                >
                  Next Ungraded
                </b-button>
              </b-button-group>
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
          <!-- while another question or filter loads, what's on screen belongs to the old selection: hide it
               (v-show keeps its editors alive) so nobody marks up or scores the wrong question -->
          <div v-if="processing" class="grading-loading-placeholder text-muted" role="status">
            <b-spinner small class="mr-2"/>
            Loading&hellip;
          </div>
          <b-container v-show="!processing" :fluid="splitView">
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
              <!-- Left: the student's open-ended work (split view only; full width below otherwise) -->
              <b-col cols="12"
                     :lg="splitView ? 7 : 12"
                     :xl="splitView ? 8 : 12"
                     :class="splitView ? 'order-1' : 'order-2'"
              >
                <div :class="{ 'submission-pane-sticky': splitView }">
                  <div v-if="splitView && viewSubmission && retrievedFromS3 && !grading[currentStudentPage - 1]['open_ended_submission']['submission']"
                       class="submission-pane-empty text-muted"
                  >
                    This student has no open-ended submission.
                  </div>
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
                  <div v-show="retrievedFromS3"
                       :class="splitView ? 'submission-pane' : 'row mt-4 d-flex justify-content-center'"
                  >
                    <div v-show="viewSubmission" :class="{ 'w-100': splitView }">
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
                        <div v-if="fullView && isOpenEndedFileSubmission && pageImages().length" class="w-100">
                          <SubmissionPagesViewer
                            :key="`pages-${grading[currentStudentPage - 1].student.user_id}-${questionView}`"
                            :pages="pageImages()"
                            :assignment-id="+assignmentId"
                            :question-id="+grading[currentStudentPage - 1]['open_ended_submission']['question_id']"
                            :student-user-id="+grading[currentStudentPage - 1].student.user_id"
                            :can-annotate="[2, 4].includes(user.role)"
                            @imageError="refreshPageImages"
                          />
                        </div>
                        <div v-else-if="fullView && isOpenEndedFileSubmission && isImageSubmission()" class="submission-image-viewer">
                          <b-alert v-if="markupUnavailableReason()" show variant="info" class="small py-2 mb-2">
                            {{ markupUnavailableReason() }}
                          </b-alert>
                          <div class="submission-image-toolbar">
                            <b-button-group size="sm">
                              <b-button variant="outline-secondary"
                                        :pressed="imageViewMode === 'width'"
                                        @click="setImageFit('width')"
                              >
                                Fit Width
                              </b-button>
                              <b-button variant="outline-secondary"
                                        :pressed="imageViewMode === 'page'"
                                        @click="setImageFit('page')"
                              >
                                Fit Page
                              </b-button>
                              <b-button variant="outline-secondary"
                                        :pressed="imageViewMode === 'zoom' && imageZoom === 1"
                                        @click="setImageZoom(1)"
                              >
                                100%
                              </b-button>
                            </b-button-group>
                            <b-button-group size="sm" class="ml-2">
                              <b-button variant="outline-secondary" aria-label="Zoom out" @click="stepImageZoom(-1)">
                                &minus;
                              </b-button>
                              <b-button variant="outline-secondary" aria-label="Zoom in" @click="stepImageZoom(1)">
                                +
                              </b-button>
                            </b-button-group>
                            <span class="ml-2 small text-muted">{{ imageZoomLabel() }} · click image to zoom</span>
                          </div>
                          <div ref="imageViewport"
                               class="submission-image-viewport"
                               :class="`mode-${imageViewMode}`"
                          >
                            <img v-show="!imageLoadError"
                                 :key="grading[currentStudentPage - 1]['open_ended_submission']['submission_url']"
                                 ref="submissionImage"
                                 alt="Student file submission"
                                 :src="grading[currentStudentPage - 1]['open_ended_submission']['submission_url']"
                                 :style="imageViewMode === 'zoom' && imageNaturalWidth ? { width: `${imageNaturalWidth * imageZoom}px` } : {}"
                                 @load="onSubmissionImageLoad"
                                 @error="onSubmissionImageError"
                                 @click="toggleImageZoom"
                            >
                            <div v-if="imageLoadError" class="p-4 text-center">
                              This image can't be displayed in the browser (for example, an iPhone HEIC photo).
                              Use <strong>Open File Submission</strong> above to download it.
                            </div>
                          </div>
                        </div>
                        <div v-else-if="fullView && isOpenEndedFileSubmission" :class="{ row: !splitView }">
                          <b-alert v-if="markupUnavailableReason()" show variant="info" class="small py-2 mb-2 w-100">
                            {{ markupUnavailableReason() }}
                          </b-alert>
                          <iframe :key="grading[currentStudentPage - 1]['open_ended_submission']['submission']"
                                  title="Open-ended submission"
                                  :class="{ 'submission-pane-frame': splitView }"
                                  :width="splitView ? '100%' : 600"
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
                    <div v-show="!viewSubmission" :class="{ 'w-100': splitView }">
                      <iframe
                        v-if="grading[currentStudentPage - 1]['open_ended_submission']['file_feedback_type'] !== 'audio'"
                        :class="{ 'submission-pane-frame': splitView }"
                        :width="splitView ? '100%' : 600"
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
                        <div v-if="showStudentNavigation && splitView && retrievedFromS3" class="text-center mt-2 mb-2">
                          <b-button-group size="sm" aria-label="Move between students">
                            <b-button variant="outline-primary"
                                      :disabled="previousUngradedStudentPage === null"
                                      @click="goToStudentPage(previousUngradedStudentPage)"
                            >
                              Previous Ungraded
                            </b-button>
                            <b-button variant="outline-primary"
                                      :disabled="currentStudentPage <= 1"
                                      @click="goToStudentPage(currentStudentPage - 1)"
                            >
                              Previous
                            </b-button>
                            <b-button variant="outline-primary"
                                      :disabled="currentStudentPage >= numStudents"
                                      @click="goToStudentPage(currentStudentPage + 1)"
                            >
                              Next
                            </b-button>
                            <b-button variant="outline-primary"
                                      :disabled="nextUngradedStudentPage === null"
                                      @click="goToStudentPage(nextUngradedStudentPage)"
                            >
                              Next Ungraded
                            </b-button>
                          </b-button-group>
                        </div>
                </div>
              </b-col>

              <!-- Right: question, scores, feedback -->
              <b-col cols="12"
                     :lg="splitView ? 5 : 12"
                     :xl="splitView ? 4 : 12"
                     :class="splitView ? 'order-2' : 'order-1'"
              >
                <b-row>
                  <b-col cols="12" :lg="splitView ? 12 : 6">
                    <b-card ref="questionCard" class="mb-3" header="default" :header-html="questionHeader">
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
                          <!-- shown while the student's requirements are being checked, instead of an empty card -->
                          <div v-if="!showDiscussIt" class="text-center text-muted py-4">
                            <b-spinner small type="grow" class="mr-1"/>
                            Loading comments...
                          </div>
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
                              <li v-show="discussItRequirementsInfo.min_number_of_attached_files > 0">
                                <CompletedIcon
                                  :completed="discussItRequirementsInfo.satisfied_min_number_of_attached_files_requirement"
                                />
                                <span
                                  :class="discussItRequirementsInfo.satisfied_min_number_of_attached_files_requirement ? 'text-success' : 'text-danger'"
                                >
                                  Attached {{ discussItRequirementsInfo.number_of_attached_files }}
                                  file<span v-if="+discussItRequirementsInfo.number_of_attached_files !== 1">s</span>,
                                  with {{ discussItRequirementsInfo.min_number_of_attached_files }} required.
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
                                <div v-if="comment.timestamps && comment.timestamps.length" class="mt-1 mb-1">
                                  <b-button v-for="timestamp in comment.timestamps"
                                            :key="`timestamp-${timestamp.id}`"
                                            size="sm"
                                            variant="outline-secondary"
                                            class="mr-1 py-0 font-weight-normal"
                                            :aria-label="`Play ${timestamp.referenced_created_by_name}'s comment from ${formatSeconds(timestamp.seconds)}`"
                                            @click="openTimestampedComment(timestamp)"
                                  >
                                    <b-icon icon="play-fill" aria-hidden="true"/>
                                    {{ timestamp.referenced_created_by_name }} {{ formatSeconds(timestamp.seconds) }}
                                  </b-button>
                                </div>
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
                                <div v-if="comment.attachments && comment.attachments.length" class="mt-1 mb-2">
                                  <div v-for="attachment in comment.attachments" :key="`attachment-${attachment.id}`" class="mb-1">
                                    <img v-if="attachment.file_kind === 'image' && attachment.file_url"
                                         :src="attachment.file_url"
                                         :alt="`Image attached by the student: ${attachment.original_filename}`"
                                         class="d-block mb-1"
                                         style="max-width: 100%; max-height: 400px; border-radius: 4px; cursor: pointer"
                                         @click="viewFileComment(attachment)"
                                    >
                                    <b-icon icon="paperclip" aria-hidden="true"/>
                                    <!-- PDFs and images open in a window with a Download button; Office files download -->
                                    <a v-if="['pdf', 'image'].includes(attachment.file_kind)"
                                       href=""
                                       @click.prevent="viewFileComment(attachment)"
                                    >{{ attachment.original_filename }}</a>
                                    <a v-else :href="attachment.download_url">{{ attachment.original_filename }}</a>
                                  </div>
                                </div>
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
                            v-show="hasScannedImage(grading[currentStudentPage - 1])"
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
                  <b-col cols="12" :lg="splitView ? 12 : 6">
                    <div class="mb-2">
                      <b-card
                        class="h-50"
                        :style="{ borderColor: cardBorderColor, borderWidth:'2px' }"
                      >
                        <template #header>
                          <h2 class="h7 mb-0">
                            Scores for {{ grading[currentStudentPage - 1]['student']['name'] }}
                            <QuestionCircleTooltip v-if="grading[currentStudentPage - 1]['student']['email']" :id="`student-info`"/>
                            <b-tooltip v-if="grading[currentStudentPage - 1]['student']['email']"
                                       :target="`student-info`"
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
                                <div class="d-flex align-items-center" :class="{ 'flex-wrap': isDynamicRubric }">
                                  <b-form-input
                                    v-show="isOpenEnded
        || (isForge && grading[currentStudentPage - 1]['open_ended_submission']['date_submitted'] )
          || discussionsByUserId.find(item => item.user_id === grading[currentStudentPage-1].student.user_id).comments"
                                    id="open_ended_score"
                                    v-model="gradingForm.file_submission_score"
                                    type="text"
                                    size="sm"
                                    :readonly="isDynamicRubric"
                                    :title="isDynamicRubric ? 'Calculated from the rubric' : ''"
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
        <b-button v-if="!isDynamicRubric"
                  size="sm"
                  class="mr-2"
                  variant="outline-success"
                  @click="submitGradingForm(true,
                                            {
                                              specialScore: 'full score',
                                              scoreType: 'file_submission_score',
                                              score: grading[currentStudentPage - 1]['open_ended_submission']['points'] * 1
                                            })"
        >Full Score</b-button>
        <b-button v-if="!isDynamicRubric"
                  size="sm"
                  variant="outline-danger"
                  @click="submitGradingForm(true,
                                            {
                                              specialScore: 'zero score',
                                              scoreType: 'file_submission_score',
                                              score: 0
                                            })"
        >
          Zero Score</b-button>
        <b-button v-if="isDynamicRubric"
                  size="sm"
                  variant="outline-danger"
                  class="text-nowrap my-1"
                  title="Clears every criterion and the adjustment, saves, and moves to the next student"
                  @click="noCreditAndNext()"
        >
          No Credit &amp; Next</b-button>
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
                            <div v-if="(isOpenEnded || isDiscussIt || isForge) && [2, 4].includes(user.role)"
                                 class="mb-2"
                            >
                              <span class="font-weight-bold mr-1">Rubric</span>
                              <b-badge v-if="rubric"
                                       :variant="isDynamicRubric ? 'info' : 'secondary'"
                                       class="mr-2"
                              >
                                {{ isDynamicRubric ? 'Dynamic' : 'Static' }}
                              </b-badge>
                              <a v-if="rubricEditPermission.canEdit && !savingDynamicRubric"
                                 id="edit-rubric-tooltip"
                                 href=""
                                 class="mr-2"
                                 :aria-label="rubric ? 'Edit Rubric' : 'Add Rubric'"
                                 @click.prevent="initEditRubric()"
                              >
                                <b-icon :icon="rubric ? 'pencil' : 'plus-circle'"
                                        class="text-muted"
                                        scale="1.1"
                                        aria-hidden="true"
                                />
                              </a>
                              <!-- disabled: a wrapper so the reason still shows on hover or keyboard focus -->
                              <span v-else
                                    id="edit-rubric-tooltip"
                                    class="mr-2"
                                    tabindex="0"
                                    role="img"
                                    :aria-label="`${rubric ? 'Edit Rubric' : 'Add Rubric'} (not available): ${editRubricTooltip}`"
                                    style="opacity:.5;cursor:not-allowed"
                              >
                                <b-icon :icon="rubric ? 'pencil' : 'plus-circle'"
                                        class="text-muted"
                                        scale="1.1"
                                        aria-hidden="true"
                                />
                              </span>
                              <b-tooltip :key="`edit-rubric-tooltip-${+rubricEditPermission.canEdit}-${+savingDynamicRubric}`"
                                         target="edit-rubric-tooltip"
                                         delay="250"
                                         triggers="hover focus"
                              >
                                {{ editRubricTooltip }}
                              </b-tooltip>
                              <template v-if="user.role === 2 && (isDynamicRubric || rubricEditPermission.hasHistory)">
                                <a id="rubric-history-tooltip"
                                   href=""
                                   aria-label="Rubric History"
                                   @click.prevent="showRubricHistory()"
                                >
                                  <b-icon icon="clock-history"
                                          class="text-muted"
                                          scale="1.1"
                                          aria-hidden="true"
                                  />
                                </a>
                                <b-tooltip target="rubric-history-tooltip"
                                           delay="250"
                                           triggers="hover focus"
                                >
                                  Rubric History
                                </b-tooltip>
                              </template>
                            </div>
                            <div
                              v-if="rubric && (isOpenEnded || isDiscussIt || isForge)"
                            >
                              <b-alert v-if="grading[currentStudentPage - 1].needs_review"
                                       show
                                       variant="warning"
                                       class="small"
                              >
                                <span v-if="isDynamicRubric">
                                  <strong>Needs review.</strong> This student kept their score when the rubric was
                                  converted to dynamic scoring: an adjustment makes up the difference. Check their
                                  criteria and save to clear this.
                                </span>
                                <span v-else-if="staticNeedsReviewMismatch">
                                  <strong>Needs review.</strong> This student kept their score of
                                  {{ formatPoints(staticNeedsReviewMismatch.score) }} when the rubric was converted to
                                  static scoring, but their criteria add up to
                                  {{ formatPoints(staticNeedsReviewMismatch.expected) }}<span
                                    v-if="staticNeedsReviewMismatch.penalty"
                                  > after the {{ formatPoints(staticNeedsReviewMismatch.penalty) }}% late penalty</span>.
                                  Check the points, then save to clear this.
                                  <b-button size="sm"
                                            variant="outline-primary"
                                            class="ml-1 mt-1"
                                            @click="useStaticCriteriaTotal()"
                                  >
                                    Use {{ formatPoints(staticNeedsReviewMismatch.expected) }}
                                  </b-button>
                                </span>
                                <span v-else>
                                  <strong>Needs review.</strong> This student kept their score when the rubric was
                                  converted to static scoring. Their criteria now add up to their score: save to clear
                                  this.
                                </span>
                              </b-alert>
                              <DynamicRubricGradingPanel
                                v-if="isDynamicRubric"
                                ref="dynamicRubricPanel"
                                :key="`dynamic-rubric-panel-${rubricPointsBreakdownIndex}-${grading[currentStudentPage - 1]['open_ended_submission']['user_id']}-${grading[currentStudentPage - 1]['open_ended_submission']['question_id']}`"
                                :rubric="rubric"
                                :assignment-id="+assignmentId"
                                :question-id="+grading[currentStudentPage - 1]['open_ended_submission']['question_id']"
                                :user-id="+grading[currentStudentPage - 1]['open_ended_submission']['user_id']"
                                :question-points="grading[currentStudentPage - 1]['open_ended_submission']['points'] * 1"
                                :auto-graded-score="gradingForm.question_submission_score"
                                :late-penalty-percent="gradingForm.applied_late_penalty"
                                @loaded="dynamicRubricLoaded"
                                @change="dynamicRubricChanged"
                              />
                              <RubricPointsBreakdown
                                v-else
                                :key="`rubric-points-breakdown-${rubricPointsBreakdownIndex}-${grading[currentStudentPage - 1]['open_ended_submission']['user_id']}-${grading[currentStudentPage - 1]['open_ended_submission']['question_id']}`"
                                :user-id="grading[currentStudentPage - 1]['open_ended_submission']['user_id']"
                                :assignment-id="+assignmentId"
                                :original-rubric="rubric"
                                :question-id="grading[currentStudentPage - 1]['open_ended_submission']['question_id']"
                                :question-points="grading[currentStudentPage - 1]['open_ended_submission']['points'] * 1 -gradingForm.question_submission_score* 1"
                                @updateOpenEndedSubmissionScore="updateOpenEndedSubmissionScore"
                                @invalidRubricPoints="staticRubricInvalid = true"
                                @setRubricPointsBreakdown="setRubricPointsBreakdown"
                                @setOriginalRubricWithMaxes="setOriginalRubricWithMaxes"
                              />
                              <div v-if="!isDynamicRubric && staticRubricSavedName" class="small text-muted mt-1" aria-live="polite">
                                <b-icon icon="check" aria-hidden="true"/> Saved {{ staticRubricSavedName }}
                              </div>
                              <div v-if="isDynamicRubric" class="small mt-1" aria-live="polite">
                                <span v-if="savingDynamicRubric" class="text-muted">
                                  <b-spinner small type="grow"/> Saving…
                                </span>
                                <span v-else-if="dynamicRubricSaveStatus === 'failed'" class="text-danger">
                                  Not saved.
                                  <a href="" @click.prevent="retryDynamicRubricAutosave()">Try again</a>
                                </span>
                                <span v-else-if="dynamicRubricUnsaved && dynamicRubricContext && !dynamicRubricCanAutosave(dynamicRubricContext)"
                                      class="text-muted"
                                >
                                  Feedback will save once you apply a criterion.
                                </span>
                                <span v-else-if="dynamicRubricUnsaved" class="text-muted">
                                  <b-spinner small type="grow"/> Saving…
                                </span>
                                <span v-else-if="dynamicRubricSaveStatus === 'saved'" class="text-muted">
                                  <b-icon icon="check" aria-hidden="true"/> Saved
                                </span>
                              </div>
                            </div>
                            <hr>
                            <b-container>
                              <b-row>
                                <b-col v-if="isOpenEndedFileSubmission">
                                  <b-button variant="outline-primary"
                                            :disabled="grading[currentStudentPage - 1]['open_ended_submission']['submission_url'] === null"
                                            size="sm"
                                            @click="openInNewTab(getFullPdfUrlAtPage(grading[currentStudentPage - 1]['open_ended_submission']['submission_url'],grading[currentStudentPage - 1]['open_ended_submission']['page'] || 1) )"
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
                      <b-button v-if="isDynamicRubric"
                                :class="{ 'disabled': currentStudentPage === numStudents }"
                                :aria-disabled="currentStudentPage === numStudents"
                                size="sm"
                                variant="success"
                                @click="goToNextStudent()"
                      >
                        Next Student
                      </b-button>
                      <b-button v-if="!isDynamicRubric"
                                variant="primary"
                                :class="{ 'disabled': noSubmission && !gradingForm.file_submission_score}"
                                :aria-disabled="noSubmission && !gradingForm.file_submission_score"
                                size="sm"
                                class="ml-1 mr-1"
                                @click="noSubmission && !gradingForm.file_submission_score ? '' :submitGradingForm(false)"
                      >
                        Submit
                      </b-button>
                      <b-button
                        v-if="!isDynamicRubric"
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
                <b-row v-if="!isDiscussIt && submissionArray.length" class="mt-2">
                  <b-col cols="12" :lg="splitView ? 12 : 6">
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
                </b-row>
              </b-col>
            </b-row>
          </b-container>
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
import DynamicRubricGradingPanel from '../../components/DynamicRubricGradingPanel.vue'
import RubricPropertiesModal from '../../components/RubricPropertiesModal.vue'
import ConsultInsight from '../../components/ConsultInsight.vue'
import QtiJsonAnswerViewer from '../../components/QtiJsonAnswerViewer.vue'
import { faExternalLinkAlt, faClock } from '@fortawesome/free-solid-svg-icons'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { roundToDecimalSigFig } from '../../helpers/Math'
import ForgeViewer from '../../components/viewers/ForgeViewer.vue'
import Transcript from '../../components/Transcript.vue'
import SubmissionPagesViewer from '../../components/SubmissionPagesViewer.vue'
import VuePdfEmbed from 'vue-pdf-embed/dist/vue2-pdf-embed'

Vue.prototype.$http = axios // needed for the audio player
export default {
  middleware: 'auth',
  components: {
    Transcript,
    SubmissionPagesViewer,
    VuePdfEmbed,
    ForgeViewer,
    FontAwesomeIcon,
    QtiJsonAnswerViewer,
    ConsultInsight,
    RubricPointsBreakdown,
    DynamicRubricGradingPanel,
    RubricPropertiesModal,
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
    // dynamic rubrics: the student whose panel is showing, and whether the grader changed their criteria
    dynamicRubricContext: null,
    dynamicRubricDirty: false,
    savingDynamicRubric: false,
    // static rubrics: the student on screen and what's changed for them, saved when the grader moves on
    staticRubricContext: null,
    staticRubricDirty: false,
    staticRubricInvalid: false,
    savingStaticRubric: false,
    staticRubricSavedName: '',
    dynamicRubricAutosaveTimer: null,
    dynamicRubricSavePromise: null,
    dynamicRubricSaveStatus: '',
    // editing the rubric from the Open Grader
    rubricEditPermission: { canEdit: false, message: '', hasHistory: false },
    keepStudentUserIdOnReload: null,
    rubricHistory: [],
    rubricHistoryLoading: false,
    // live "the rubric changed" prompt (Centrifugo)
    rubricUpdatesCentrifuge: null,
    rubricUpdatesSubscription: null,
    rubricVersionsSavedHere: [],
    rubricUpdatePendingWhileEditing: null,
    // dynamic rubrics: students flagged when a static rubric was converted
    needsReviewCount: 0,
    // static rubrics: a save held back until the grader decides about a criteria/score mismatch
    pendingStaticMismatchSave: null,
    rubricUpdatedElsewhere: { name: '', time: '', unsavedChanges: false },
    reloadingAfterRubricUpdate: false,
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
    // Image submission viewer: 'width' | 'page' (fit modes) or 'zoom' (fixed scale)
    imageViewMode: 'width',
    imageFitPreference: 'width',
    imageZoom: 1,
    imageNaturalWidth: 0,
    imageLoadError: false,
    imageRetried: false,
    showDiscussIt: false,
    discussItRequirementsInfo: {},
    discussItRequirementsKey: '',
    activeDiscussion: {},
    // the audio/video comment (at a moment) that a reply's timestamp points to
    timestampedComment: { name: '', time: '', src: '' },
    // a Discuss-it file comment (PDF or image) being viewed
    activeFileComment: {},
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
  computed: {
    ...mapGetters({
      user: 'auth/user'
    }),
    // Open-ended questions get the side-by-side layout: student work on the left,
    // question/scores/feedback on the right. Everything else keeps the stacked layout.
    splitView () {
      return this.isOpenEnded
    },
    showStudentNavigation () {
      return this.gradeView === 'allStudents' && this.numStudents > 1
    },
    // page numbers (1-based) of the students who submitted something and don't have a score yet
    ungradedStudentPages () {
      const pages = []
      this.grading.forEach((item, index) => {
        const openEndedSubmission = item.open_ended_submission
        if (!openEndedSubmission) {
          return
        }
        const manualGrading = openEndedSubmission.open_ended_submission_type === 'no submission, manual grading'
        let submitted = manualGrading || Boolean(openEndedSubmission.submission)
        if (this.isForge) {
          submitted = Boolean(openEndedSubmission.date_submitted)
        }
        if (this.isDiscussIt) {
          const discussion = (this.discussionsByUserId || []).find(value => value.user_id === item.student.user_id)
          submitted = Boolean(discussion && discussion.comments && discussion.comments.length)
        }
        if (submitted && [null, undefined, '', 'N/A'].includes(openEndedSubmission.file_submission_score)) {
          pages.push(index + 1)
        }
      })
      return pages
    },
    previousUngradedStudentPage () {
      const pages = this.ungradedStudentPages.filter(page => page < this.currentStudentPage)
      return pages.length ? pages[pages.length - 1] : null
    },
    nextUngradedStudentPage () {
      const page = this.ungradedStudentPages.find(page => page > this.currentStudentPage)
      return page === undefined ? null : page
    },
    // static rubric, flagged Needs review: the criteria (with any late penalty) don't add up to the score
    staticNeedsReviewMismatch () {
      const current = this.grading && this.grading[this.currentStudentPage - 1]
      if (!current || !current.needs_review || this.isDynamicRubric || !this.rubric) {
        return null
      }
      const items = this.gradingForm.rubric_points_breakdown
      const score = this.gradingForm.file_submission_score
      if (!Array.isArray(items) || !items.length || score === null || score === undefined || score === '' || isNaN(+score)) {
        return null
      }
      const total = items.reduce((sum, item) => sum + (+item.points || 0), 0)
      const penalty = [null, undefined, ''].includes(this.gradingForm.applied_late_penalty)
        ? 0
        : +String(this.gradingForm.applied_late_penalty).replace('%', '') || 0
      const expected = Math.round(total * (1 - penalty / 100) * 100) / 100
      return Math.abs(expected - +score) >= 0.01 ? { expected, score: +score, penalty } : null
    },
    currentQuestionPoints () {
      const current = this.grading && this.grading[this.currentStudentPage - 1]
      return current && current['open_ended_submission'] ? +current['open_ended_submission']['points'] || 0 : 0
    },
    rubricVersion () {
      if (!this.rubric) {
        return 0
      }
      try {
        const rubric = JSON.parse(this.rubric)
        return rubric && rubric.scoring_style === 'dynamic' ? +rubric.version || 0 : 0
      } catch (error) {
        return 0
      }
    },
    // the student being graded has criteria, feedback or scores that haven't been saved yet
    dynamicRubricUnsaved () {
      const context = this.dynamicRubricContext
      return Boolean(this.isDynamicRubric && context && this.dynamicRubricFormChanged(context))
    },
    editRubricTooltip () {
      if (this.savingDynamicRubric) {
        return 'Saving this student first...'
      }
      if (!this.rubricEditPermission.canEdit) {
        return this.rubricEditPermission.message || "You can't edit this rubric."
      }
      return this.rubric ? 'Edit Rubric' : 'Add Rubric'
    },
    isDynamicRubric () {
      if (!this.rubric) {
        return false
      }
      try {
        return JSON.parse(this.rubric).scoring_style === 'dynamic'
      } catch (error) {
        return false
      }
    }
  },
  watch: {
    showRubricProperties (newVal) {
      // a rubric change that arrived while this grader was editing is shown once the editor closes
      if (!newVal && this.rubricUpdatePendingWhileEditing) {
        const message = this.rubricUpdatePendingWhileEditing
        this.rubricUpdatePendingWhileEditing = null
        this.rubricUpdated(message)
      }
    },
    'gradingForm.file_submission_score' (newVal, oldVal) {
      this.totalScore = +this.gradingForm.question_submission_score + +newVal || 0
    },
    'gradingForm.question_submission_score' (newVal, oldVal) {
      this.totalScore = +this.gradingForm.file_submission_score + +newVal || 0
      this.scheduleDynamicRubricAutosave(1000)
    },
    plainTextFeedback () {
      this.scheduleDynamicRubricAutosave(1500)
    },
    richTextFeedback () {
      this.scheduleDynamicRubricAutosave(1500)
    },
    cannedResponse () {
      this.scheduleDynamicRubricAutosave(500)
    }
  },
  created () {
    window.addEventListener('keydown', this.arrowListener)
    window.addEventListener('beforeunload', this.dynamicRubricBeforeUnload)
  },
  destroyed () {
    window.removeEventListener('keydown', this.arrowListener)
    window.removeEventListener('message', this.receiveMessage)
    window.removeEventListener('beforeunload', this.dynamicRubricBeforeUnload)
    this.stopRubricUpdates()
  },
  async beforeRouteLeave (to, from, next) {
    // dynamic rubrics: leaving the Open Grader saves the student being graded, like moving to another student
    await this.saveDynamicRubricStudentIfNeeded()
    await this.saveStaticRubricStudentIfNeeded()
    next()
  },
  mounted () {
    applyWarningsVisibility(this.user)
    this.routeStudentUserId = this.$route.params.studentUserId
    this.routeQuestionId = this.$route.params.questionId

    this.renderMathJax = +localStorage.renderMathJax === 1
    try {
      if (['width', 'page'].includes(localStorage.graderImageFit)) {
        this.imageFitPreference = localStorage.graderImageFit
        this.imageViewMode = localStorage.graderImageFit
      }
    } catch (e) {
      // storage unavailable; use default fit
    }
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
    formatSeconds (totalSeconds) {
      totalSeconds = Math.floor(+totalSeconds || 0)
      const hours = Math.floor(totalSeconds / 3600)
      const minutes = Math.floor((totalSeconds % 3600) / 60)
      const seconds = String(totalSeconds % 60).padStart(2, '0')
      return hours ? `${hours}:${String(minutes).padStart(2, '0')}:${seconds}` : `${minutes}:${seconds}`
    },
    viewFileComment (comment) {
      this.activeFileComment = comment
      this.$bvModal.show('modal-grader-file-comment')
    },
    openTimestampedComment (timestamp) {
      // start a few seconds early so the lead-up to the moment can be heard (same as the student view)
      const startTime = Math.max(0, timestamp.seconds - 5)
      this.timestampedComment = {
        name: timestamp.referenced_created_by_name,
        time: this.formatSeconds(timestamp.seconds),
        src: `/discussion-comments/media-player/discussion-comment-id/${timestamp.referenced_discussion_comment_id}/is-phone/0?start_time=${startTime}`
      }
      this.$bvModal.show('modal-timestamped-comment')
    },
    viewInNewTab (url) {
      window.open(url, '_blank')
    },
    // The submission can come back as text or as already-decoded data (an object or a number), depending on the
    // question type; calling .includes on anything but text broke the whole page.
    hasScannedImage (grading) {
      const submission = grading && grading.auto_graded_submission && grading.auto_graded_submission.submission
      if (!submission) {
        return false
      }
      const text = typeof submission === 'string' ? submission : JSON.stringify(submission)
      return Boolean(text) && text.includes('structure_s3_key')
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
      this.staticRubricDirty = true
      this.staticRubricInvalid = false
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
          // reload so the grader uses the rubric just saved, staying on the same student
          await this.reloadGradingKeepingStudent()
        }
      } catch (error) {
        this.$noty.error(error.message)
      }
    },
    formatPoints (number) {
      return Math.round(+number * 100) / 100
    },
    useStaticCriteriaTotal () {
      if (this.staticNeedsReviewMismatch) {
        this.gradingForm.file_submission_score = this.staticNeedsReviewMismatch.expected
      }
    },
    async resolveStaticMismatch (useCriteriaTotal) {
      if (useCriteriaTotal) {
        this.useStaticCriteriaTotal()
      }
      this.$bvModal.hide('modal-static-needs-review-mismatch')
      const pending = this.pendingStaticMismatchSave || { next: false, prepopulatedScore: {}, justShowErrorMessage: false }
      this.pendingStaticMismatchSave = null
      await this.submitGradingForm(pending.next, pending.prepopulatedScore, pending.justShowErrorMessage, true)
    },
    async dynamicRubricSaved (rubric, publishedVersion) {
      // saved (and graded students rescored) from this page: don't prompt this grader to reload for it
      const version = publishedVersion || (rubric && rubric.version)
      if (version) {
        this.rubricVersionsSavedHere.push(+version)
      }
      this.showRubricProperties = false
      await this.reloadGradingKeepingStudent()
    },
    async reloadGradingKeepingStudent () {
      const current = this.grading[this.currentStudentPage - 1]
      this.keepStudentUserIdOnReload = current && current.student ? current.student.user_id : null
      this.processing = true
      await this.getGrading(false)
      this.rubricPointsBreakdownIndex++
    },
    async getRubricEditPermission () {
      this.rubricEditPermission = { canEdit: false, message: '', hasHistory: false }
      if (!this.questionView || ![2, 4].includes(this.user.role)) {
        return
      }
      try {
        const { data } = await axios.get(`/api/assignments/${this.assignmentId}/questions/${this.questionView}/dynamic-rubric`)
        if (data.type === 'success') {
          this.rubricEditPermission = {
            canEdit: Boolean(data.can_edit),
            message: data.can_edit_message || '',
            hasHistory: Boolean(data.has_history),
            settings: data.rubric_permissions
              ? { ...data.rubric_permissions, default_scoring_style: data.default_scoring_style }
              : null
          }
        } else {
          this.rubricEditPermission = { canEdit: false, message: data.message || '', hasHistory: false }
        }
      } catch (error) {
        this.$noty.error(error.message)
      }
    },
    async initEditRubric () {
      if (!this.rubricEditPermission.canEdit) {
        return
      }
      if (this.isDynamicRubric && this.dynamicRubricContext) {
        // save the student being graded first, so the preview counts their criteria
        await this.saveDynamicRubricStudentIfNeeded()
        // reload the panel so this student is tracked again if the edit is cancelled
        this.rubricPointsBreakdownIndex++
      }
      this.showRubricProperties = true
    },
    async showRubricHistory () {
      this.rubricHistory = []
      this.rubricHistoryLoading = true
      this.$bvModal.show('modal-dynamic-rubric-history')
      try {
        const { data } = await axios.get(`/api/assignments/${this.assignmentId}/questions/${this.questionView}/dynamic-rubric/history`)
        if (data.type !== 'success') {
          this.$noty.error(data.message)
          this.$bvModal.hide('modal-dynamic-rubric-history')
          return
        }
        this.rubricHistory = data.history
      } catch (error) {
        this.$noty.error(error.message)
      } finally {
        this.rubricHistoryLoading = false
      }
    },
    rubricHistoryChanges (entry) {
      const formatPoints = points => Math.round(+points * 100) / 100
      const pointsLabel = points => `${formatPoints(points)} point${formatPoints(points) === 1 ? '' : 's'}`
      const after = entry.after || {}
      const afterItems = after.rubric_items || []
      if (entry.action === 'convert') {
        return [`Converted the static rubric to dynamic scoring with ${afterItems.length} criteri${afterItems.length === 1 ? 'on' : 'a'}.`]
      }
      if (entry.action === 'delete_override_clear') {
        return ["Deleted the overriding rubric and cleared the scores of the students graded with it."]
      }
      if (entry.action === 'switch_rubric_clear') {
        return [`Switched to ${after.switched_to === 'override' ? 'the overriding rubric' : "the question's rubric"} and cleared the scores of the students graded with the old one.`]
      }
      if (entry.action === 'convert_to_static') {
        const removed = ((entry.before || {}).rubric_items || []).filter(item => !(+item.points > 0)).map(item => `"${item.title}"`)
        const lines = [`Converted the dynamic rubric to static scoring with ${afterItems.length} criteri${afterItems.length === 1 ? 'on' : 'a'}.`]
        if (removed.length) {
          lines.push(`Removed the feedback-only (0-point) ${removed.length === 1 ? 'criterion' : 'criteria'} ${removed.join(', ')}.`)
        }
        return lines
      }
      if (!entry.before || entry.before.scoring_style !== 'dynamic') {
        return [`Started the dynamic rubric with ${afterItems.length} criteri${afterItems.length === 1 ? 'on' : 'a'}.`]
      }
      const beforeItems = entry.before.rubric_items || []
      const changes = []
      const beforeById = {}
      beforeItems.forEach(item => {
        beforeById[item.id] = item
      })
      const afterIds = afterItems.map(item => item.id)
      afterItems.forEach(item => {
        const old = beforeById[item.id]
        if (!old) {
          changes.push(`Added "${item.title}" (${pointsLabel(item.points)}).`)
          return
        }
        if (old.title !== item.title) {
          changes.push(`Renamed "${old.title}" to "${item.title}".`)
        }
        if (+old.points !== +item.points) {
          changes.push(`Changed "${item.title}" from ${formatPoints(old.points)} to ${pointsLabel(item.points)}.`)
        }
        if ((old.description || '') !== (item.description || '')) {
          changes.push(`Edited the description of "${item.title}".`)
        }
      })
      beforeItems.filter(item => !afterIds.includes(item.id)).forEach(item => {
        changes.push(`Deleted "${item.title}" (${pointsLabel(item.points)}).`)
      })
      const keptBefore = beforeItems.map(item => item.id).filter(id => afterIds.includes(id))
      const keptAfter = afterIds.filter(id => keptBefore.includes(id))
      if (keptBefore.join('|') !== keptAfter.join('|')) {
        changes.push('Reordered the criteria.')
      }
      if (Boolean(entry.before.rubric_shown) !== Boolean(after.rubric_shown)) {
        changes.push(after.rubric_shown ? 'Showed the rubric to students.' : 'Hid the rubric from students.')
      }
      return changes.length ? changes : ['Saved without changes to the criteria.']
    },
    async connectRubricUpdates () {
      // a quiet connection: if live updates aren't available, grading carries on without the prompt
      if (this.rubricUpdatesCentrifuge) {
        return this.rubricUpdatesCentrifuge
      }
      if (typeof window.Centrifuge === 'undefined') {
        return null
      }
      try {
        const { data } = await axios.get('/api/centrifugo/token')
        if (data.type !== 'success') {
          return null
        }
        const protocol = window.location.hostname === 'local.adapt' ? 'ws' : 'wss'
        const centrifuge = new window.Centrifuge(`${protocol}://${data.domain}/connection/websocket`, { token: data.token })
        centrifuge.connect()
        this.rubricUpdatesCentrifuge = centrifuge
      } catch (error) {
        console.log(error)
        return null
      }
      return this.rubricUpdatesCentrifuge
    },
    async listenForRubricUpdates () {
      if (!this.questionView || !(this.isOpenEnded || this.isDiscussIt || this.isForge)) {
        this.unsubscribeRubricUpdates()
        return
      }
      const channel = `rubric-updated-${this.assignmentId}-${this.questionView}`
      if (this.rubricUpdatesSubscription && this.rubricUpdatesSubscription.channel === channel) {
        return
      }
      this.unsubscribeRubricUpdates()
      const centrifuge = await this.connectRubricUpdates()
      if (!centrifuge) {
        return
      }
      try {
        const subscription = centrifuge.getSubscription(channel) || centrifuge.newSubscription(channel)
        const rubricUpdated = this.rubricUpdated
        subscription.on('publication', function (ctx) {
          rubricUpdated(ctx.data)
        })
        subscription.subscribe()
        this.rubricUpdatesSubscription = subscription
      } catch (error) {
        console.log(error)
      }
    },
    unsubscribeRubricUpdates () {
      const subscription = this.rubricUpdatesSubscription
      this.rubricUpdatesSubscription = null
      if (!subscription) {
        return
      }
      try {
        subscription.removeAllListeners()
        subscription.unsubscribe()
        if (this.rubricUpdatesCentrifuge) {
          this.rubricUpdatesCentrifuge.removeSubscription(subscription)
        }
      } catch (error) {
        console.log(error)
      }
    },
    stopRubricUpdates () {
      this.unsubscribeRubricUpdates()
      if (this.rubricUpdatesCentrifuge) {
        try {
          this.rubricUpdatesCentrifuge.disconnect()
        } catch (error) {
          console.log(error)
        }
        this.rubricUpdatesCentrifuge = null
      }
    },
    async rubricUpdated (message) {
      // the message only says which rubric changed and its new version
      if (!message || +message.question_id !== +this.questionView || +message.assignment_id !== +this.assignmentId) {
        return
      }
      const version = +message.version
      if (this.rubricVersionsSavedHere.includes(version) || version <= this.rubricVersion) {
        return
      }
      if (this.showRubricProperties) {
        // their own save would be refused anyway (the rubric moved on); prompt when the editor closes
        this.rubricUpdatePendingWhileEditing = message
        return
      }
      const context = this.dynamicRubricContext
      this.rubricUpdatedElsewhere = {
        name: '',
        time: '',
        unsavedChanges: Boolean(this.isDynamicRubric && context && this.dynamicRubricFormChanged(context))
      }
      try {
        const { data } = await axios.get(`/api/assignments/${this.assignmentId}/questions/${this.questionView}/dynamic-rubric/latest-change`)
        if (data.type === 'success' && data.latest_change) {
          this.rubricUpdatedElsewhere.name = data.latest_change.name
          this.rubricUpdatedElsewhere.time = data.latest_change.time
        }
      } catch (error) {
        console.log(error)
      }
      this.$bvModal.show('modal-rubric-updated-elsewhere')
    },
    async reloadAfterRubricUpdatedElsewhere () {
      this.reloadingAfterRubricUpdate = true
      // unsaved criteria were picked from the old rubric, so they're dropped rather than saved
      this.dynamicRubricContext = null
      this.dynamicRubricDirty = false
      try {
        await this.reloadGradingKeepingStudent()
      } finally {
        this.reloadingAfterRubricUpdate = false
        this.$bvModal.hide('modal-rubric-updated-elsewhere')
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
      const questionId = this.grading[this.currentStudentPage - 1]['open_ended_submission']['question_id']
      const userId = this.grading[this.currentStudentPage - 1]['open_ended_submission']['user_id']
      // only show the loading message when switching to a different student; this is called more than once
      // for the same student when the page loads, and that should refresh quietly instead of flashing
      const requirementsKey = `${questionId}-${userId}`
      if (requirementsKey !== this.discussItRequirementsKey) {
        this.showDiscussIt = false
      }
      try {
        const { data } = await axios.get(`/api/discussion-comments/assignment/${this.assignmentId}/question/${questionId}/user/${userId}/satisfied`)
        if (data.type === 'error') {
          this.$noty.error(data.message)
        } else {
          this.discussItCompletionCriteria = data.completion_criteria
          this.discussItRequirementsInfo = data.satisfied_requirements
          this.discussItRequirementsKey = requirementsKey
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
      if (this.isDynamicRubric) {
        // dynamic rubrics: only record the percentage; the server applies it whenever it calculates the score
        const enteredDynamicLatePenalty = String(this.grading[this.currentStudentPage - 1]['open_ended_submission']['applied_late_penalty'] ?? '')
          .replace('%', '')
          .trim()
        if (!/^\d+$/.test(enteredDynamicLatePenalty) || Number(enteredDynamicLatePenalty) > 100) {
          this.$noty.error('Please enter a whole-number percentage from 0 to 100.')
          return false
        }
        this.gradingForm.applied_late_penalty = Number(enteredDynamicLatePenalty)
        this.scheduleDynamicRubricAutosave(0)
        return
      }
      if ((1 * this.grading[this.currentStudentPage - 1]['open_ended_submission']['file_submission_score'] || 0) > 0) {
        // read the number as typed ("10", "10%", " 10 % ") instead of stripping every non-digit,
        // which turned 1.5% into 15%; the database stores whole-number percentages only
        const enteredLatePenalty = String(this.grading[this.currentStudentPage - 1]['open_ended_submission']['applied_late_penalty'] ?? '')
          .replace('%', '')
          .trim()
        if (!/^\d+$/.test(enteredLatePenalty) || Number(enteredLatePenalty) > 100) {
          this.$noty.error('Please enter a whole-number percentage from 0 to 100.')
          return false
        }
        let appliedLatePenalty = Number(enteredLatePenalty)
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
      const active = document.activeElement
      // don't switch students while typing (feedback box, markup text) or with a modal such as the markup editor open
      let inTextArea = (active && (active.id === 'text_comments' ||
          ['INPUT', 'TEXTAREA', 'SELECT'].includes(active.tagName) ||
          active.isContentEditable)) ||
        Boolean(document.querySelector('.modal.show'))
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
            this.$set(this.grading[this.currentStudentPage - 1]['open_ended_submission'], 'page_images', files.page_images || [])
            this.$set(this.grading[this.currentStudentPage - 1]['open_ended_submission'], 'page_images_status', files.page_images_status || null)
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
    // Page images of the student's PDF for this question (empty until they've been rendered, or for old
    // start-page-only assignments, in which case the PDF view is used)
    pageImages () {
      const current = this.grading[this.currentStudentPage - 1]
      return (current && current.open_ended_submission && current.open_ended_submission.page_images) || []
    },
    // Why a file submission is shown as the original file instead of as pages that can be marked up ('' when
    // there's nothing to explain). Uses the conversion status that get-files-from-s3 returns with the files.
    markupUnavailableReason () {
      const current = this.grading[this.currentStudentPage - 1]
      const submission = current && current.open_ended_submission
      if (!submission || !submission.got_files || this.pageImages().length) {
        return ''
      }
      const isPdf = /\.pdf$/i.test(submission.submission || '')
      // only these are converted (ConvertSubmissionPdfToImages::canConvert); other files are never marked up
      if (!isPdf && !/\.(jpe?g|png|gif)$/i.test(submission.submission || '')) {
        return ''
      }
      const original = isPdf ? 'the PDF' : 'the original image'
      switch (submission.page_images_status) {
        case 'pending':
        case 'processing':
          return `This file is still being prepared for marking up. Showing ${original} for now; reload in a minute to mark it up.`
        case 'failed':
          return `This file couldn't be converted for marking up, so markup isn't available. Showing ${original} instead.`
        case 'completed':
          // converted, but this question only has a starting page (assigned before page ranges existed)
          return 'The student assigned only a starting page for this question, so its pages can\'t be marked up. Showing the PDF from that page.'
        default:
          return `This file was uploaded before marking up was available. Showing ${original}.`
      }
    },
    // The page image links are temporary; on a load error fetch fresh ones once per student
    async refreshPageImages () {
      const current = this.grading[this.currentStudentPage - 1]?.open_ended_submission
      if (!current || current.page_images_refreshed) {
        return
      }
      this.$set(current, 'page_images_refreshed', true)
      current.got_files = false
      await this.getFilesFromS3()
    },
    isImageSubmission () {
      const submission = this.grading[this.currentStudentPage - 1]?.open_ended_submission?.submission
      return typeof submission === 'string' && /\.(jpe?g|png|gif|webp|bmp|heic|heif)$/i.test(submission)
    },
    resetImageViewer () {
      this.imageViewMode = this.imageFitPreference
      this.imageZoom = 1
      this.imageNaturalWidth = 0
      this.imageLoadError = false
      this.imageRetried = false
    },
    async onSubmissionImageError () {
      // The usual cause is an expired temporary S3 link: fetch a fresh one and retry once
      if (this.imageRetried) {
        this.imageLoadError = true
        return
      }
      this.imageRetried = true
      const page = this.currentStudentPage
      const current = this.grading[page - 1]?.open_ended_submission
      if (!current) return
      const oldUrl = current.submission_url
      current.got_files = false
      await this.getFilesFromS3()
      // If the grader moved to another student meanwhile, leave that student's viewer alone
      if (page !== this.currentStudentPage) return
      if (current.submission_url === oldUrl) {
        // No new link came back, so retrying would fail the same way
        this.imageLoadError = true
      }
      // Otherwise the :key on the <img> (its URL) changes, so Vue loads the fresh link
    },
    onSubmissionImageLoad (event) {
      this.imageNaturalWidth = event.target.naturalWidth
      this.imageLoadError = false
    },
    setImageFit (mode) {
      this.imageViewMode = mode
      this.imageFitPreference = mode
      try {
        localStorage.graderImageFit = mode
      } catch (e) {
        // storage unavailable; preference just won't persist
      }
    },
    currentImageScale () {
      // Scale the image is displayed at right now, relative to its natural size
      const img = this.$refs.submissionImage
      if (!img || !this.imageNaturalWidth) return 1
      return this.imageViewMode === 'zoom' ? this.imageZoom : img.clientWidth / this.imageNaturalWidth
    },
    setImageZoom (zoom, anchor = null) {
      const viewport = this.$refs.imageViewport
      const img = this.$refs.submissionImage
      const clamped = Math.min(4, Math.max(0.1, zoom))
      // Remember which point of the image sits under the anchor (cursor or viewport center)
      let fx = 0.5
      let fy = 0.5
      let ax = viewport ? viewport.clientWidth / 2 : 0
      let ay = viewport ? viewport.clientHeight / 2 : 0
      if (img && viewport) {
        const rect = img.getBoundingClientRect()
        const vRect = viewport.getBoundingClientRect()
        if (anchor) {
          ax = anchor.clientX - vRect.left
          ay = anchor.clientY - vRect.top
        }
        fx = rect.width ? (vRect.left + ax - rect.left) / rect.width : 0.5
        fy = rect.height ? (vRect.top + ay - rect.top) / rect.height : 0.5
      }
      this.imageZoom = clamped
      this.imageViewMode = 'zoom'
      this.$nextTick(() => {
        if (!img || !viewport) return
        viewport.scrollLeft = fx * img.offsetWidth - ax
        viewport.scrollTop = fy * img.offsetHeight - ay
      })
    },
    stepImageZoom (direction) {
      const factor = direction > 0 ? 1.25 : 0.8
      this.setImageZoom(this.currentImageScale() * factor)
    },
    toggleImageZoom (event) {
      if (this.imageViewMode === 'zoom') {
        this.imageViewMode = this.imageFitPreference
        return
      }
      // From a fit mode: zoom in to full resolution (at least 2x the fitted size) at the clicked point
      this.setImageZoom(Math.max(1, this.currentImageScale() * 2), event)
    },
    imageZoomLabel () {
      if (this.imageViewMode === 'width') return 'Fit width'
      if (this.imageViewMode === 'page') return 'Fit page'
      return `${Math.round(this.imageZoom * 100)}%`
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
    async submitGradingForm (next, prepopulatedScore = {}, justShowErrorMessage = false, mismatchDecided = false) {
      if (this.isDynamicRubric) {
        // this save covers whatever an autosave was about to send
        clearTimeout(this.dynamicRubricAutosaveTimer)
        if (this.dynamicRubricSavePromise) {
          await this.dynamicRubricSavePromise
        }
      }
      if (!mismatchDecided && !prepopulatedScore.specialScore && this.staticNeedsReviewMismatch) {
        // a flagged student whose criteria don't match their score: ask before marking them reviewed
        this.pendingStaticMismatchSave = { next, prepopulatedScore, justShowErrorMessage }
        this.$bvModal.show('modal-static-needs-review-mismatch')
        return false
      }
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
          // The backend sets this when this student has no open-ended submission
          // and the question isn't set to a manual-grading type.
          // Nothing was saved, so clear the score the grader entered (e.g. from Zero Score).
          this.gradingForm.file_submission_score = null
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
        if (['success', 'info'].includes(data.type)) {
          this.staticRubricDirty = false
          this.staticRubricInvalid = false
        }
        if (data.type === 'success') {
          const savedStudent = this.grading[this.currentStudentPage - 1]
          if (!this.isDynamicRubric && savedStudent && savedStudent.needs_review && this.gradingForm.rubric_points_breakdown) {
            // static: saving the breakdown marks the student reviewed (the server clears the flag)
            savedStudent.needs_review = false
            this.needsReviewCount = Math.max(0, this.needsReviewCount - 1)
          }
          if (data.dynamic_rubric_score) {
            // the server's calculated score is the one that counts
            this.gradingForm.file_submission_score = data.dynamic_rubric_score.final
          }
          if (this.dynamicRubricContext && this.dynamicRubricContext.index === this.currentStudentPage - 1) {
            this.dynamicRubricDirty = false
            this.dynamicRubricSaveStatus = 'saved'
            if (this.gradingForm.dynamic_rubric) {
              this.dynamicRubricContext.savedCriteria = this.dynamicRubricCriteriaKey(
                this.gradingForm.dynamic_rubric.applied_item_ids, this.gradingForm.dynamic_rubric.adjustment)
            }
            this.dynamicRubricContext.exists = this.dynamicRubricContext.exists || Boolean(this.gradingForm.dynamic_rubric)
            this.rememberDynamicRubricStudentSaved(this.dynamicRubricContext.index, {
              textFeedback: this.gradingForm.textFeedback,
              textFeedbackEditor: this.gradingForm.text_feedback_editor,
              fileSubmissionScore: this.gradingForm.file_submission_score,
              data
            })
          }
          if ((this.isOpenEnded || this.isDiscussIt) && this.grading[this.currentStudentPage - 1]['open_ended_submission']) {
            this.grading[this.currentStudentPage - 1]['open_ended_submission']['file_submission_score'] = this.gradingForm.file_submission_score
            this.grading[this.currentStudentPage - 1]['open_ended_submission']['saved_applied_late_penalty'] = this.gradingForm.applied_late_penalty === undefined ? null : this.gradingForm.applied_late_penalty
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
        } else if (data.type === 'info' && next && this.currentStudentPage < this.numStudents) {
          // nothing changed for this student ("Neither the total score..."), but still move on as asked
          this.currentStudentPage++
          await this.changePage()
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
    dynamicRubricLoaded (state) {
      // the panel has loaded this student's applied criteria
      const index = this.currentStudentPage - 1
      const openEndedSubmission = this.grading[index]['open_ended_submission']
      this.dynamicRubricContext = {
        index,
        assignment_id: +this.assignmentId,
        question_id: openEndedSubmission.question_id,
        user_id: openEndedSubmission.user_id,
        name: this.grading[index].student.name,
        exists: state.exists,
        // what's saved for this student, so changing a criterion and changing it back isn't "unsaved"
        savedCriteria: this.dynamicRubricCriteriaKey(state.exists ? state.applied_item_ids : [], state.exists ? state.adjustment : 0)
      }
      this.dynamicRubricDirty = false
      this.dynamicRubricSaveStatus = ''
      this.gradingForm.rubric_points_breakdown = null
      this.gradingForm.dynamic_rubric = state.exists
        ? { applied_item_ids: state.applied_item_ids, adjustment: state.adjustment, rubric_version: state.rubric_version }
        : null
    },
    dynamicRubricChanged (state) {
      if (!this.dynamicRubricContext) {
        return
      }
      if (state.by_grader) {
        this.dynamicRubricDirty = this.dynamicRubricCriteriaKey(state.applied_item_ids, state.adjustment) !==
          this.dynamicRubricContext.savedCriteria
      }
      // only once the student has (or is being given) a rubric score; otherwise leave them ungraded
      if (state.by_grader || this.dynamicRubricContext.exists || this.dynamicRubricDirty) {
        this.gradingForm.rubric_points_breakdown = null
        this.gradingForm.dynamic_rubric = {
          applied_item_ids: state.applied_item_ids,
          adjustment: state.adjustment,
          rubric_version: state.rubric_version
        }
        this.gradingForm.file_submission_score = state.score
        this.scheduleDynamicRubricAutosave()
      }
    },
    dynamicRubricCriteriaKey (appliedItemIds, adjustment) {
      return JSON.stringify([(appliedItemIds || []).map(String).sort(), Math.round(+(adjustment || 0) * 100) / 100])
    },
    plainFeedbackText (feedback) {
      return String(feedback || '').replace(/<[^>]*>/g, ' ').replace(/&nbsp;/g, ' ').replace(/\s+/g, ' ').trim()
    },
    dynamicRubricFormChanged (context) {
      // anything changed for this student since it was loaded or last saved: criteria, adjustment,
      // feedback, auto-graded score or late penalty
      const saved = this.grading[context.index]
      if (!saved) {
        return false
      }
      if (this.dynamicRubricDirty) {
        return true
      }
      const openEndedSubmission = saved['open_ended_submission']
      const feedback = this.getTextFeedback(this.textFeedbackMode)
      if (feedback !== false && feedback !== undefined &&
        this.plainFeedbackText(feedback) !== this.plainFeedbackText(openEndedSubmission.text_feedback)) {
        return true
      }
      const savedAutoGradedScore = saved['auto_graded_submission'] ? saved['auto_graded_submission']['score'] : null
      if (savedAutoGradedScore !== null && savedAutoGradedScore !== undefined &&
        +this.gradingForm.question_submission_score !== +savedAutoGradedScore) {
        return true
      }
      const toPenalty = value => [null, undefined, ''].includes(value) ? null : +value
      return toPenalty(this.gradingForm.applied_late_penalty) !== toPenalty(openEndedSubmission.saved_applied_late_penalty)
    },
    rememberDynamicRubricStudentSaved (index, { textFeedback, textFeedbackEditor, fileSubmissionScore, data }) {
      const saved = this.grading[index]
      if (!saved) {
        return
      }
      saved['open_ended_submission']['file_submission_score'] = fileSubmissionScore
      saved['open_ended_submission']['text_feedback'] = textFeedback
      saved['open_ended_submission']['text_feedback_editor'] = textFeedbackEditor
      saved['open_ended_submission']['saved_applied_late_penalty'] = [null, undefined, ''].includes(this.gradingForm.applied_late_penalty)
        ? null
        : +this.gradingForm.applied_late_penalty
      saved['open_ended_submission']['grader_name'] = data.grader_name
      saved['last_graded'] = data.last_graded
      if (saved.needs_review && data.dynamic_rubric_score) {
        // the server clears the flag whenever a grader saves the student's criteria
        saved.needs_review = false
        this.needsReviewCount = Math.max(0, this.needsReviewCount - 1)
      }
      if (saved['auto_graded_submission']) {
        saved['auto_graded_submission']['score'] = this.gradingForm.question_submission_score
      }
    },
    // dynamic rubrics save themselves: changes wait for a short pause, then save without a success message
    scheduleDynamicRubricAutosave (delay = 500) {
      if (!this.isDynamicRubric) {
        return
      }
      clearTimeout(this.dynamicRubricAutosaveTimer)
      this.dynamicRubricAutosaveTimer = setTimeout(() => this.autosaveDynamicRubricStudent(), delay)
    },
    dynamicRubricCanAutosave (context) {
      // a student with no rubric score stays ungraded, so feedback typed for them waits for a criterion
      return context.exists || Boolean(this.gradingForm.dynamic_rubric)
    },
    async autosaveDynamicRubricStudent () {
      if (this.dynamicRubricSavePromise) {
        await this.dynamicRubricSavePromise
      }
      const context = this.dynamicRubricContext
      if (!context || !this.isDynamicRubric || context.index !== this.currentStudentPage - 1 ||
        !this.dynamicRubricCanAutosave(context) || !this.dynamicRubricFormChanged(context)) {
        return
      }
      const saved = await this.postDynamicRubricStudent(context)
      // something changed while that save was on its way
      if (saved && this.dynamicRubricContext === context && this.dynamicRubricFormChanged(context)) {
        this.scheduleDynamicRubricAutosave()
      }
    },
    retryDynamicRubricAutosave () {
      this.dynamicRubricSaveStatus = ''
      this.autosaveDynamicRubricStudent()
    },
    async postDynamicRubricStudent (context) {
      const save = this.sendDynamicRubricStudent(context)
      this.dynamicRubricSavePromise = save
      const saved = await save
      this.dynamicRubricSavePromise = null
      return saved
    },
    async sendDynamicRubricStudent (context) {
      this.savingDynamicRubric = true
      const openEndedSubmission = this.grading[context.index]['open_ended_submission']
      const feedback = this.getTextFeedback(this.textFeedbackMode)
      const textFeedback = feedback === false || feedback === undefined ? openEndedSubmission.text_feedback : feedback
      const textFeedbackEditor = this.textFeedbackMode === 'rich_text' ? 'rich' : 'plain'
      const dynamicRubric = this.gradingForm.dynamic_rubric
      const sentCriteria = dynamicRubric
        ? this.dynamicRubricCriteriaKey(dynamicRubric.applied_item_ids, dynamicRubric.adjustment)
        : null
      let saved = false
      try {
        const { data } = await axios.post('/api/grading', {
          assignment_id: context.assignment_id,
          question_id: context.question_id,
          user_id: context.user_id,
          question_submission_score: this.gradingForm.question_submission_score,
          applied_late_penalty: this.gradingForm.applied_late_penalty,
          text_feedback_editor: textFeedbackEditor,
          textFeedback: textFeedback,
          dynamic_rubric: dynamicRubric
        })
        if (['success', 'info'].includes(data.type)) {
          saved = true
          this.rememberDynamicRubricStudentSaved(context.index, {
            textFeedback,
            textFeedbackEditor,
            fileSubmissionScore: data.dynamic_rubric_score ? data.dynamic_rubric_score.final : this.gradingForm.file_submission_score,
            data
          })
          if (sentCriteria !== null) {
            context.savedCriteria = sentCriteria
            context.exists = true
          }
          if (this.dynamicRubricContext === context) {
            const form = this.gradingForm.dynamic_rubric
            this.dynamicRubricDirty = Boolean(form) &&
              this.dynamicRubricCriteriaKey(form.applied_item_ids, form.adjustment) !== context.savedCriteria
          }
        } else if (data.manual_grading_error) {
          this.$noty.error(`${context.name} was not saved: they have no open-ended submission for this question.`)
        } else {
          this.$noty.error(`${context.name} was not saved: ${data.message}`)
        }
      } catch (error) {
        this.$noty.error(`${context.name} was not saved: ${error.message}`)
      } finally {
        this.savingDynamicRubric = false
      }
      this.dynamicRubricSaveStatus = saved ? 'saved' : 'failed'
      return saved
    },
    async saveDynamicRubricStudentIfNeeded () {
      clearTimeout(this.dynamicRubricAutosaveTimer)
      if (this.dynamicRubricSavePromise) {
        await this.dynamicRubricSavePromise
      }
      const context = this.dynamicRubricContext
      if (!context) {
        return
      }
      // forget the student first so a second navigation can't save them twice
      this.dynamicRubricContext = null
      if (!this.isDynamicRubric || !this.dynamicRubricFormChanged(context)) {
        this.dynamicRubricDirty = false
        return
      }
      this.dynamicRubricDirty = false
      await this.postDynamicRubricStudent(context)
    },
    staticRubricFormChanged (context) {
      const saved = this.grading[context.index]
      if (!saved) {
        return false
      }
      if (this.staticRubricDirty) {
        return true
      }
      const toScore = value => [null, undefined, '', 'N/A'].includes(value) ? null : +value
      return toScore(this.gradingForm.file_submission_score) !== toScore(saved['open_ended_submission']['file_submission_score']) ||
        this.dynamicRubricFormChanged(context)
    },
    // Static rubrics: saves the student being left, if anything changed. The grader stays put until it's saved, so a
    // failed save loses nothing. Returns whether it's fine to move on.
    async saveStaticRubricStudentIfNeeded () {
      const context = this.staticRubricContext
      if (!context || this.isDynamicRubric) {
        return true
      }
      const targetPage = this.currentStudentPage
      if (this.savingStaticRubric) {
        this.currentStudentPage = context.index + 1
        return false
      }
      if (!this.staticRubricFormChanged(context)) {
        return true
      }
      this.currentStudentPage = context.index + 1
      if (this.staticRubricInvalid) {
        this.$noty.error(`${context.name} was not saved: please fix the rubric points first.`)
        return false
      }
      const savedStudent = this.grading[context.index]
      if (savedStudent.needs_review && this.staticNeedsReviewMismatch) {
        this.pendingStaticMismatchSave = { next: false, prepopulatedScore: {}, justShowErrorMessage: false }
        this.$bvModal.show('modal-static-needs-review-mismatch')
        return false
      }
      const openEndedSubmission = savedStudent['open_ended_submission']
      const feedback = this.getTextFeedback(this.textFeedbackMode)
      const textFeedback = feedback === false || feedback === undefined ? openEndedSubmission.text_feedback : feedback
      const textFeedbackEditor = this.textFeedbackMode === 'rich_text' ? 'rich' : 'plain'
      const fileSubmissionScore = this.gradingForm.file_submission_score
      const sentBreakdown = this.staticRubricDirty
      const payload = {
        assignment_id: this.assignmentId,
        question_id: context.question_id,
        user_id: context.user_id,
        question_submission_score: this.gradingForm.question_submission_score,
        file_submission_score: fileSubmissionScore,
        applied_late_penalty: this.gradingForm.applied_late_penalty,
        text_feedback_editor: textFeedbackEditor,
        textFeedback
      }
      if (sentBreakdown) {
        payload.rubric_points_breakdown = this.gradingForm.rubric_points_breakdown
      }
      this.savingStaticRubric = true
      try {
        const { data } = await axios.post('/api/grading', payload)
        if (!['success', 'info'].includes(data.type)) {
          this.$noty.error(data.manual_grading_error
            ? `${context.name} was not saved: they have no open-ended submission for this question.`
            : `${context.name} was not saved: ${data.message}`)
          return false
        }
        this.rememberDynamicRubricStudentSaved(context.index, { textFeedback, textFeedbackEditor, fileSubmissionScore, data })
        if (savedStudent.needs_review && sentBreakdown) {
          savedStudent.needs_review = false
          this.needsReviewCount = Math.max(0, this.needsReviewCount - 1)
        }
        this.staticRubricDirty = false
        this.staticRubricSavedName = context.name
        this.currentStudentPage = targetPage
        return true
      } catch (error) {
        const errors = error.response && error.response.data && error.response.data.errors
        const message = errors ? Object.values(errors)[0] : error.message
        this.$noty.error(`${context.name} was not saved: ${message}`)
        return false
      } finally {
        this.savingStaticRubric = false
      }
    },
    goToStudentPage (page) {
      if (page === null || page < 1 || page > this.numStudents || page === this.currentStudentPage) {
        return
      }
      this.currentStudentPage = page
      this.changePage()
    },
    goToNextStudent () {
      if (this.currentStudentPage < this.numStudents) {
        this.currentStudentPage++
        this.changePage()
      }
    },
    dynamicRubricBeforeUnload (event) {
      const staticChanged = this.staticRubricContext && this.staticRubricFormChanged(this.staticRubricContext)
      if (staticChanged || (this.isDynamicRubric && this.dynamicRubricContext && this.dynamicRubricFormChanged(this.dynamicRubricContext))) {
        // the browser shows its own "leave site?" warning
        event.preventDefault()
        event.returnValue = ''
      }
    },
    async noCreditAndNext () {
      if (this.$refs.dynamicRubricPanel) {
        this.$refs.dynamicRubricPanel.noCredit()
      }
      await this.submitGradingForm(true)
    },
    async changePage () {
      // dynamic rubrics: save the student we're leaving before the form switches to the next one
      await this.saveDynamicRubricStudentIfNeeded()
      if (!await this.saveStaticRubricStudentIfNeeded()) {
        return false
      }
      if (!this.isDynamicRubric) {
        // the next student's breakdown comes from the rubric panel; never reuse the last student's
        this.gradingForm.rubric_points_breakdown = null
      }
      this.staticRubricContext = null
      this.staticRubricDirty = false
      this.staticRubricInvalid = false
      this.gradingForm.dynamic_rubric = null
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
      if (this.needsReviewCount > 0 || this.gradeView === 'needsReview') {
        this.gradeViews.push({ text: `Needs Review (${this.needsReviewCount})`, value: 'needsReview' })
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
      // Leave the box blank when no score has been entered (1 * null would show a misleading 0)
      const savedFileSubmissionScore = this.grading[this.currentStudentPage - 1]['open_ended_submission']['file_submission_score']
      this.gradingForm.file_submission_score = [null, undefined, '', 'N/A'].includes(savedFileSubmissionScore)
        ? null
        : 1 * savedFileSubmissionScore
      this.gradingForm.question_submission_score = !this.grading[this.currentStudentPage - 1]['auto_graded_submission']
        ? null
        : 1 * this.grading[this.currentStudentPage - 1]['auto_graded_submission']['score']
      // each student starts with their own saved late penalty, never the one applied to the previous student
      const savedAppliedLatePenalty = this.grading[this.currentStudentPage - 1]['open_ended_submission']['saved_applied_late_penalty']
      this.gradingForm.applied_late_penalty = savedAppliedLatePenalty === undefined ? null : savedAppliedLatePenalty

      this.setTextFeedback(textFeedback)
      this.gradingForm.textFeedback = this.grading[this.currentStudentPage - 1]['open_ended_submission']['text_feedback']
      if (this.rubric && !this.isDynamicRubric) {
        const openEndedSubmission = this.grading[this.currentStudentPage - 1]['open_ended_submission']
        this.staticRubricContext = {
          index: this.currentStudentPage - 1,
          question_id: openEndedSubmission.question_id,
          user_id: openEndedSubmission.user_id,
          name: this.grading[this.currentStudentPage - 1].student.name
        }
      }
      console.log(this.grading[this.currentStudentPage - 1]['open_ended_submission'])

      this.openEndedType = this.grading[this.currentStudentPage - 1]['open_ended_submission'].open_ended_submission_type
      // Reset before the files load, so a fast image error isn't wiped out afterwards
      this.resetImageViewer()
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
      // dynamic rubrics: switching question, section or view leaves the current student, so save them first
      await this.saveDynamicRubricStudentIfNeeded()
      await this.saveStaticRubricStudentIfNeeded()
      this.staticRubricContext = null
      // set by reloadGradingKeepingStudent: after a rubric change, stay on this student
      const keepStudentUserId = this.keepStudentUserIdOnReload
      this.keepStudentUserIdOnReload = null
      if (this.routeQuestionId && this.routeStudentUserId) {
        this.questionView = this.$route.params.questionId
        this.gradeView = 'allStudents'
        this.sectionId = 0
      }
      let gradeView
      gradeView = this.gradeView
      if (['gradedDiscussions', 'ungradedDiscussions', 'needsReview'].includes(this.gradeView)) {
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
          // keep the student's own saved penalty (a number or null) separately from the display text below,
          // so each save sends this student's penalty and never the previous student's
          const savedAppliedLatePenalty = grading['open_ended_submission']['applied_late_penalty']
          data.grading[i]['open_ended_submission']['saved_applied_late_penalty'] =
            [null, undefined, ''].includes(savedAppliedLatePenalty) ? null : Number(savedAppliedLatePenalty)
          if (grading['open_ended_submission']['late_penalty_percent']) {
            let latePenaltyPercent = grading['open_ended_submission']['applied_late_penalty']
              ? grading['open_ended_submission']['applied_late_penalty']
              : grading['open_ended_submission']['late_penalty_percent']
            data.grading[i]['open_ended_submission']['applied_late_penalty'] = `${latePenaltyPercent}%`
          }
        }
        this.grading = data.grading
        this.needsReviewCount = data.needs_review_count || 0
        if (this.gradeView === 'needsReview') {
          this.grading = this.grading.filter(item => item.needs_review)
          if (!this.grading.length) {
            this.showNoAutoGradedOrOpenSubmissionsExistAlert = true
            this.isLoading = false
            this.processing = false
            return false
          }
        }
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
        if (keepStudentUserId) {
          const keptStudentIndex = this.grading.findIndex(item => item.student && +item.student.user_id === +keepStudentUserId)
          if (keptStudentIndex > -1) {
            this.currentStudentPage = keptStudentIndex + 1
          }
        }

        // loop through questions, inner loop through students, if match, then set question and student)

        if (this.routeQuestionId && this.routeStudentUserId) {
          this.setQuestionAndStudentByQuestionIdAndStudentUserId(this.routeQuestionId, this.routeStudentUserId)
          this.routeQuestionId = null
          this.routeStudentUserId = null
        }
        let questionOptions = JSON.parse(JSON.stringify(this.questionOptions))
        this.solutions = [questionOptions.find(question => +question.value === +this.questionView).solution]
        await this.changePage()
        await this.getRubricEditPermission()
        this.listenForRubricUpdates()

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

/* Image submission viewer */
.submission-image-viewer {
  width: min(900px, 95vw);
}

.submission-image-toolbar {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  margin-bottom: 0.5rem;
}

.submission-image-viewport {
  height: min(80vh, 900px);
  overflow: auto;
  border: 1px solid #dee2e6;
  border-radius: 4px;
  background: #f8f9fa;
}

.submission-image-viewport img {
  display: block;
  margin: 0 auto;
}

.submission-image-viewport.mode-width img {
  width: 100%;
  height: auto;
  cursor: zoom-in;
}

.submission-image-viewport.mode-page {
  display: flex;
  align-items: center;
  justify-content: center;
}

.submission-image-viewport.mode-page img {
  max-width: 100%;
  max-height: 100%;
  cursor: zoom-in;
}

/* Split view: student work on the left, grading on the right */
.submission-pane .submission-image-viewer {
  width: 100%;
}

.submission-pane .submission-image-viewport,
.submission-pane-frame {
  height: calc(100vh - 140px);
  min-height: 500px;
}

.submission-pane-frame {
  width: 100%;
  border: 1px solid #dee2e6;
  border-radius: 4px;
}

/* holds the space the hidden grading panels used, so the page doesn't jump while loading */
.grading-loading-placeholder {
  display: flex;
  align-items: flex-start;
  justify-content: center;
  min-height: 60vh;
  padding-top: 48px;
}

.submission-pane-empty {
  padding: 3rem 1rem;
  text-align: center;
  border: 1px dashed #dee2e6;
  border-radius: 4px;
}

@media (min-width: 992px) {
  /* Keep the submission in view while scrolling through the grading panels */
  .submission-pane-sticky {
    position: sticky;
    top: 1rem;
  }
}

.submission-image-viewport.mode-zoom img {
  max-width: none;
  height: auto;
  cursor: zoom-out;
}
</style>
