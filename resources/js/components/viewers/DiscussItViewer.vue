<template>
  <div>
    <AllFormErrors :modal-id="'modal-form-errors-comments'" :all-form-errors="allFormErrors" />
    <AllFormErrors :modal-id="'modal-form-errors-discussion-settings'" :all-form-errors="allFormErrors" />
    <b-modal id="modal-cannot-delete-comment"
             title="Cannot delete comment"
             size="lg"
             no-close-on-backdrop
    >
      <p>
        You cannot delete this comment since it would cause you to lose the credit received for this question.
        However, you may reach out to your instructor and ask them to delete it for you.
      </p>
      <template #modal-footer>
        <b-button size="sm"
                  variant="primary"
                  @click="$bvModal.hide('modal-cannot-delete-comment')"
        >
          OK
        </b-button>
      </template>
    </b-modal>
    <b-modal id="modal-discussion-comment-submission-not-accepted"
             title="Submission Not Accepted"
             size="lg"
             hide-footer
             no-close-on-backdrop
    >
      <b-alert show variant="danger">
        {{ discussionCommentSubmissionResultsError }}
      </b-alert>
      <template #modal-footer>
        <b-button size="sm"
                  variant="primary"
                  @click="$bvModal.hide('modal-discussion-comment-submission-not-accepted')"
        >
          OK
        </b-button>
      </template>
    </b-modal>
    <b-modal id="modal-discussion-comment-submission-accepted"
             title="Submission Accepted"
             size="lg"
             no-close-on-backdrop
             :hide-footer="!+discussItSettingsForm.completion_criteria"
    >
      <div v-if="false">
        {{ discussionCommentSubmissionResults }}
        {{ completionRequirements }}
      </div>
      <table v-if="+discussItSettingsForm.completion_criteria" class="table table-striped table-responsive">
        <thead>
        <tr>
          <th scope="col">
            Description
          </th>
          <th scope="col">
            Minimum Number Required
          </th>
          <th scope="col">
            Number Submitted
          </th>
          <th scope="col">
            Requirement Satisfied
          </th>
        </tr>
        </thead>
        <tr
          v-show="completionRequirements.find(item => item.key === 'min_number_of_initiated_discussion_threads')"
          :class="discussionCommentSubmissionResults.satisfied_min_number_of_initiated_discussion_threads_requirement ? 'text-success' : 'text-danger'"
        >
          <th>Initiate Thread</th>
          <th>{{ discussionCommentSubmissionResults.min_number_of_initiated_discussion_threads }}</th>
          <th>{{ discussionCommentSubmissionResults.number_of_initiated_discussion_threads }}</th>
          <th>
            {{
              discussionCommentSubmissionResults.satisfied_min_number_of_initiated_discussion_threads_requirement ? 'Yes' : 'No'
            }}
          </th>
        </tr>
        <tr
          v-show="completionRequirements.find(item => item.key === 'min_number_of_replies')"
          :class="discussionCommentSubmissionResults.satisfied_min_number_of_replies_requirement ? 'text-success' : 'text-danger'"
        >
          <th>Reply to Thread</th>
          <th>
            {{
              discussionCommentSubmissionResults.min_number_of_replies
            }}
          </th>
          <th>{{ discussionCommentSubmissionResults.number_of_replies_that_satisfied_the_requirements }}</th>
          <th>
            {{
              discussionCommentSubmissionResults.satisfied_min_number_of_replies_requirement ? 'Yes' : 'No'
            }}
          </th>
        </tr>
        <tr
          v-show="completionRequirements.find(item => item.key === 'min_number_of_initiate_or_reply_in_threads')"
          :class="discussionCommentSubmissionResults.satisfied_min_number_of_initiate_or_reply_in_threads_requirement ? 'text-success' : 'text-danger'"
        >
          <th>Participate (Initiate/Reply)</th>
          <th>
            {{
              discussionCommentSubmissionResults.min_number_of_initiate_or_reply_in_threads
            }}
          </th>
          <th>
            {{
              discussionCommentSubmissionResults.number_of_initiate_or_reply_in_threads_that_satisfied_the_requirements
            }}
          </th>
          <th>
            {{
              discussionCommentSubmissionResults.satisfied_min_number_of_initiate_or_reply_in_threads_requirement ? 'Yes' : 'No'
            }}
          </th>
        </tr>
      </table>
      <template #modal-footer>
        <b-button size="sm"
                  variant="primary"
                  @click="$bvModal.hide('modal-discussion-comment-submission-accepted')"
        >
          OK
        </b-button>
      </template>
    </b-modal>
    <b-modal id="modal-update-text-comment"
             :title="`Comment created by ${activeDiscussionComment.created_by_name} on ${activeDiscussionComment.created_at}`"
             size="lg"
             no-close-on-backdrop
    >
      <DiscussItSatisfiesRequirement v-if="+discussItSettingsForm.completion_criteria"
                                     :min-number-of-words="+minNumberOfWords"
                                     :comment-form-text="commentForm.text"
                                     :comment-type="'text'"
      />

      <ckeditor
        id="discuss_it_text"
        ref="discuss_it_text"
        v-model="commentForm.text"
        tabindex="0"
        required
        :config="discussItEditorConfig"
        :class="{ 'is-invalid': commentForm.errors.has('text')}"
        class="mb-2"
        @namespaceloaded="onCKEditorNamespaceLoaded"
        @ready="handleFixCKEditorWithPasteWarning"
        @keydown="commentForm.errors.clear('text')"
      />
      <template #modal-footer>
        <b-button size="sm"
                  @click="$bvModal.hide('modal-update-text-comment')"
        >
          Cancel
        </b-button>
        <b-button size="sm"
                  variant="primary"
                  @click="saveComment()"
        >
          Save
        </b-button>
      </template>
    </b-modal>
    <b-modal id="modal-confirm-delete-comment"
             title="Confirm Delete Comment"
             size="lg"
             no-close-on-backdrop
    >
      <b-alert :show="alreadyScoredWarning.length > 0" variant="danger">
        {{ alreadyScoredWarning }}
      </b-alert>
      <p>Please confirm that would like to delete the comment {{ confirmDeleteCommentText }}:</p>
      <div v-show="activeDiscussionComment.text" v-html="activeDiscussionComment.text" />
      <div v-if="activeDiscussionComment.file">
        <iframe
          v-resize="{ log: false }"
          :src="`/discussion-comments/media-player/filename/${activeDiscussionComment.file}/is-phone/${+this.isPhone()}`"
          width="100%"
          frameborder="0"
          allowfullscreen
        />
      </div>
      <template #modal-footer>
        <b-button size="sm"
                  @click="$bvModal.hide('modal-confirm-delete-comment')"
        >
          Cancel
        </b-button>
        <b-button size="sm"
                  variant="danger"
                  @click="deleteComment()"
        >
          Delete
        </b-button>
      </template>
    </b-modal>
    <b-modal id="modal-listen-or-view-comment"
             :title="`Comment by ${activeDiscussionComment.created_by_name} at ${activeDiscussionComment.created_at}`"
             size="lg"
             no-close-on-backdrop
             @shown="currentFile = ''"
             @hidden="reRecording=false"
    >
      <iframe v-if="!reRecording"
              :key="`transcript-key-${listenOrViewCommentKey}`"
              v-resize="{ log: false }"
              :src="`/discussion-comments/media-player/discussion-comment-id/${activeDiscussionComment.id}/is-phone/${+this.isPhone()}`"
              width="100%"
              frameborder="0"
              allowfullscreen
      />
      <DiscussItSatisfiesRequirement v-if="reRecording && Boolean(+discussItSettingsForm.completion_criteria)"
                                     ref="discussItSatisfiesRequirement"
                                     :key="`discussItSatisfiesRequirement-${discussItSatisfiesRequirementKey}`"
                                     :milliseconds-time-until-requirement-satisfied="millisecondsTimeUntilRequirementSatisfied"
                                     :human-readable-time-until-requirement-satisfied="humanReadableTimeUntilRequirementSatisfied"
                                     :comment-type="commentType"
                                     :show-satisfies-requirement-timer="showSatisfiesRequirementTimer"
                                     @setRequirementSatisfied="setRequirementSatisfied"
      />

      <div v-if="commentType === 'audio'">
        <DiscussItCommentAndSubmitWorkUpload v-if="reRecording"
                                             :key="'re-record-audio'"
                                             :comment-type="'audio'"
                                             :assignment-id="assignmentId"
                                             :question-id="questionId"
                                             @saveUploadedAudioVideoComment="saveUploadedAudioVideoComment"
        />
        <div v-if="isPhone()">
          <NativeAudioVideoRecorder v-if="reRecording"
                                    :key="`new-comment-${commentType}`"
                                    :recording-type="'audio'"
                                    :assignment-id="+assignmentId"
                                    @saveComment="saveComment"
                                    @startVideoRecording="startVideoRecording"
                                    @stopVideoRecording="stopVideoRecording"
                                    @updateDiscussionCommentVideo="updateDiscussionCommentVideo"
          />
        </div>
        <div v-else>
          <audio-recorder
            v-if="reRecording"
            id="discuss-it-recorder"
            ref="recorder"
            :upload-url="`/api/discussion-comments/assignment/${assignmentId}/question/${questionId}/audio`"
            :attempts="1"
            :time="3"
            class="m-auto"
            :show-download-button="false"
            :after-recording="afterRecording"
            :before-recording="beforeRecording"
            :successful-upload="successfulRecordingUpload"
            :failed-upload="failedRecordingUpload"
            :mic-failed="micFailed"
          />
        </div>
      </div>
      <div v-if="commentType === 'video'">
        <DiscussItCommentAndSubmitWorkUpload v-if="reRecording"
                                             :key="'re-record-video'"
                                             :comment-type="'video'"
                                             :assignment-id="assignmentId"
                                             :question-id="questionId"
                                             @saveUploadedAudioVideoComment="saveUploadedAudioVideoComment"
        />
        <NativeAudioVideoRecorder v-if="reRecording"
                                  key="update-video-comment"
                                  :recording-type="'video'"
                                  :assignment-id="+assignmentId"
                                  :active-discussion-comment="activeDiscussionComment"
                                  @saveComment="saveComment"
                                  @startVideoRecording="startVideoRecording"
                                  @stopVideoRecording="stopVideoRecording"
        />
      </div>
      <div v-if="user.role === 2 && !reRecording">
        <Transcript :active-transcript="activeDiscussionComment.transcript"
                    :active-media="activeDiscussionComment"
                    :model="'DiscussionComment'"
                    @hideMediaModal="hideMediaModal"
                    @updateTranscriptInMedia="updateTranscriptInMedia"
                    @removeCurrentTranscript="removeCurrentTranscript"
        />
      </div>
      <template #modal-footer>
        <span v-show="commentType === 'video' || commentType === 'audio' && !stoppedAudioRecording">
          <b-button v-if="showAction('editComment', activeDiscussionComment.created_by_user_id)" variant="danger"
                    size="sm"
                    @click="reRecording=true"
          >
            Re-record
          </b-button>
          <span v-if="activeDiscussionComment.transcript">
            <a v-show="false" id="download-transcript"
               :href="`/api/discussion-comments/${activeDiscussionComment.id}/download-transcript`"
            >Download transcript</a>
            <b-button size="sm" variant="info" @click="downloadTranscript"> Download Transcript</b-button>
          </span>
          <b-button size="sm" variant="primary"
                    @click="$bvModal.hide('modal-listen-or-view-comment')"
          >
            OK
          </b-button>
        </span>
        <span v-show="commentType === 'audio' && stoppedAudioRecording">
          <span class="mr-2">
            <b-button
              variant="primary"
              size="sm"
              @click="saveAudio"
            >
              Save
            </b-button>
          </span>
          <b-button
            size="sm"
            variant="outline-danger"
            @click="reRecordAudio"
          >
            Delete and re-record
          </b-button>
        </span>
      </template>
    </b-modal>
    <b-modal id="modal-discuss-it-settings"
             title="Settings"
             size="lg"
             no-close-on-backdrop
             @hidden="getDiscussItSettings"
             @shown="initDiscussItSettings"
    >
      <b-container>
        <b-card header="default" header-html="<h2 class='h7'>General</h2>" class="mb-3">
          <b-card-text>
            <b-form-group
              label-cols-sm="5"
              label-cols-lg="4"
              label-for="number_of_groups"
              label-size="sm"
              label-align="right"
            >
              <template #label>
                Number of Groups
                <QuestionCircleTooltip :id="'number-of-groups-tooltip'" />
                <b-tooltip target="number-of-groups-tooltip" triggers="hover focus" delay="500">
                  If you choose more than 1 group, ADAPT will randomly assign students to the different groups allowing
                  for more manageable discussions in larger classes.
                </b-tooltip>
              </template>
              <div class="flex d-inline-flex">
                <b-form-input
                  v-model="discussItSettingsForm.number_of_groups"
                  style="width:50px"
                  size="sm"
                  :class="{ 'is-invalid': discussItSettingsForm.errors.has('number_of_groups') }"
                  @keydown="discussItSettingsForm.errors.clear('number_of_groups')"
                />
                <ErrorMessage
                  :message="discussItSettingsForm.errors.get('number_of_groups')"
                />
              </div>
            </b-form-group>
            <b-form-group
              label-cols-sm="5"
              label-cols-lg="4"
              label-size="sm"
              label-align="right"
            >
              <template #label>
                Language
                <QuestionCircleTooltip :id="'language-tooltip'" />
                <b-tooltip target="language-tooltip" triggers="hover focus" delay="500">
                  We use AI to create audio/video transcriptions. To obtain the best quality transcription, please
                  specify
                  the language of the audio/video comments. If the comments may contain multiple languages, then
                  please
                  choose the 'Multiple' option. Note that in this case transcription results may not be as expected.
                </b-tooltip>
              </template>
              <b-form-select v-model="discussItSettingsForm.language"
                             :options="languageOptions"
                             size="sm"
                             :class="{ 'is-invalid': discussItSettingsForm.errors.has('language') }"
                             style="width:200px"
                             @change="discussItSettingsForm.errors.clear('language')"
              />
              <has-error :form="discussItSettingsForm" field="language'" />
            </b-form-group>
            <b-form-radio-group v-model="discussItSettingsForm.auto_grade"
                                class="mb-2"
            >
              <label class="col-sm-5 col-lg-4 col-form-label col-form-label-sm text-right">Auto-grade
                <QuestionCircleTooltip :id="'autograde-tooltip'" />
                <b-tooltip target="autograde-tooltip" triggers="hover focus" delay="500">
                  If you choose "yes", once a student completes the criteria above, they will get full credit for the
                  question.
                </b-tooltip>
              </label>
              <b-form-radio name="auto-grade-discuss-it" value="1">
                Yes
              </b-form-radio>
              <b-form-radio name="auto-grade-discuss-it" value="0">
                No
              </b-form-radio>
            </b-form-radio-group>
            <b-form-radio-group v-model="discussItSettingsForm.completion_criteria"
                                class="mb-2"
            >
              <label class="col-sm-5 col-lg-4 col-form-label col-form-label-sm text-right">Completion Criteria
                <QuestionCircleTooltip :id="'show-completion-criteria-tooltip'" />
                <b-tooltip target="show-completion-criteria-tooltip" triggers="hover focus" delay="500">
                  Please choose "yes" if you would like to show your students the completion criteria (minimum number of
                  words
                  or minimum length of audio).
                </b-tooltip>
              </label>
              <b-form-radio name="show-completion-criteria" value="1">
                Yes
              </b-form-radio>
              <b-form-radio name="show-completion-criteria" value="0">
                No
              </b-form-radio>
            </b-form-radio-group>
          </b-card-text>
          <div v-if="!+discussItSettingsForm.completion_criteria"
               class="pb-1 flex d-inline-flex col-form-label col-form-label-sm"
          >
            <label for="response-modes" class="mr-2">Students may respond using:
              <QuestionCircleTooltip
                id="response-modes-tooltip"
              />
              <b-tooltip target="response-modes-tooltip"
                         delay="250"
                         triggers="hover focus"
              >Choose the type of comments that students are allowed to make.
              </b-tooltip>
            </label>
            <b-form-checkbox-group
              id="response-modes"
              v-model="discussItSettingsForm.response_modes"
              :options="responseModeOptions"
              class="mb-3"
              value-field="item"
              text-field="name"
              @change="discussItSettingsForm.errors.clear('response_modes')"
            />
            <ErrorMessage :message="discussItSettingsForm.errors.get('response_modes')" />
          </div>
        </b-card>
        <b-card header="default" header-html="<h2 class='h7'>Student Actions</h2>">
          <b-card-text>
            <b-form-group
              label-cols-sm="5"
              label-cols-lg="4"
              label-for="students_can_edit_comments"
              label="Students can edit comments"
              label-size="sm"
              label-align="right"
            >
              <template #label>
                Students can edit comments
                <QuestionCircleTooltip id="edit-comments-tooltip" />
                <b-tooltip target="edit-comments-tooltip"
                           delay="250"
                           triggers="hover focus"
                >
                  With this option enabled, students can edit their comments if the assignment is open. Instructors can
                  edit
                  comments
                  at any time.
                </b-tooltip>
              </template>
              <b-form-radio-group
                id="students_can_edit_comments"
                v-model="discussItSettingsForm.students_can_edit_comments"
                class="pt-1"
                @change="discussItSettingsForm.errors.clear('students_can_edit_comments')"
              >
                <b-form-radio name="students_can_edit_comments" value="1">
                  Yes
                </b-form-radio>
                <b-form-radio name="students_can_edit_comments" value="0">
                  No
                </b-form-radio>
              </b-form-radio-group>
              <ErrorMessage :message="discussItSettingsForm.errors.get('students_can_edit_comments')" />
            </b-form-group>
            <b-form-group
              label-cols-sm="5"
              label-cols-lg="4"
              label-for="students_can_delete_comments"
              label-size="sm"
              label-align="right"
            >
              <template #label>
                Students can delete comments
                <QuestionCircleTooltip id="delete-comments-tooltip" />
                <b-tooltip target="delete-comments-tooltip"
                           delay="250"
                           triggers="hover focus"
                >
                  With this option enabled, students can delete their comments if the assignment is open. Instructors
                  can
                  delete comments
                  at any time.
                </b-tooltip>
              </template>
              <b-form-radio-group
                id="students_can_delete_comments"
                v-model="discussItSettingsForm.students_can_delete_comments"
                class="pt-1"
                @change="discussItSettingsForm.errors.clear('students_can_delete_comments')"
              >
                <b-form-radio name="students_can_delete_comments" value="1">
                  Yes
                </b-form-radio>
                <b-form-radio name="students_can_delete_comments" value="0">
                  No
                </b-form-radio>
              </b-form-radio-group>
              <ErrorMessage :message="discussItSettingsForm.errors.get('students_can_delete_comments')" />
              <has-error :form="discussItSettingsForm" field="students_can_delete_comments" />
            </b-form-group>
          </b-card-text>
        </b-card>
        <div v-if="+discussItSettingsForm.completion_criteria" class="pt-3">
          <b-card header="default" header-html="<h2 class='h7'>Completion Criteria</h2>">
            <b-card-text>
              <b-alert :show="discussionCommentsExist" variant="info">
                Students have already submitted discussion comments which may cause a mismatch in student scores if you
                change the settings below.
                Any scoring issues that may arise can be fixed
                using the Open Grader.
              </b-alert>
              <b-form-group
                class="pl-4"
                label-cols-sm="1"
                label-cols-lg="1"
                label-for="number_of_initiated_discussion_threads"
                label-size="sm"
                label-align="right"
                label="Initiate"
              >
                <div class="flex d-inline-flex">
                  <b-form-input
                    v-model="discussItSettingsForm.min_number_of_initiated_discussion_threads"
                    style="width:50px"
                    size="sm"
                    :class="{ 'is-invalid': discussItSettingsForm.errors.has('min_number_of_initiated_discussion_threads') }"
                    @keydown="discussItSettingsForm.errors.clear('min_number_of_initiated_discussion_threads')"
                  />
                  <div class="col-form-label col-form-label-sm text-right pl-2">
                    {{
                      +discussItSettingsForm.min_number_of_initiated_discussion_threads === 1
                        ? 'discussion thread with new comment(s)' : 'discussion threads with new comment(s)'
                    }}
                    <QuestionCircleTooltip :id="'discussion-thread-tooltip'" />
                    <b-tooltip target="discussion-thread-tooltip" triggers="hover focus" delay="500">
                      A discussion thread can be initiated by either you or any student. If you enter a 0, this will not
                      be considered in the
                      completion criteria.<br><br>Note that by initiating a thread, a student will also be submitting a
                      comment on that thread.
                    </b-tooltip>
                  </div>
                </div>
              </b-form-group>
              <div>
                <ErrorMessage
                  :message="discussItSettingsForm.errors.get('min_number_of_initiated_discussion_threads')"
                />
              </div>
              <b-form-group
                class="pl-4"
                label-cols-sm="1"
                label-cols-lg="1"
                label-for="number_of_min_replies"
                label-size="sm"
                label-align="right"
                label="Submit"
              >
                <div class="flex d-inline-flex">
                  <b-form-input
                    v-model="discussItSettingsForm.min_number_of_replies"
                    style="width:50px"
                    size="sm"
                    :class="{ 'is-invalid': discussItSettingsForm.errors.has('min_number_of_replies') }"
                    @keydown="discussItSettingsForm.errors.clear('min_number_of_replies')"
                  />
                  <div class="col-form-label col-form-label-sm text-right pl-2">
                    {{
                      +discussItSettingsForm.min_number_of_replies === 1
                        ? 'reply to comments in existing threads' : 'replies to comments in existing threads'
                    }}
                    <QuestionCircleTooltip :id="'min-number-of-min-replies-tooltip'" />
                    <b-tooltip target="min-number-of-min-replies-tooltip" triggers="hover focus" delay="500">
                      If you enter a 0, this will not be considered in the
                      completion criteria.
                    </b-tooltip>
                  </div>
                </div>
              </b-form-group>
              <div>
                <ErrorMessage
                  :message="discussItSettingsForm.errors.get('min_number_of_replies')"
                />
              </div>
              <b-form-group
                label-cols-sm="5"
                label-cols-lg="4"
                label-for="min_number_of_initiate_or_reply_in_threads"
                label-size="sm"
                label-align="right"
                label="Participate (initiate or reply) in"
              >
                <div class="flex d-inline-flex">
                  <b-form-input
                    v-model="discussItSettingsForm.min_number_of_initiate_or_reply_in_threads"
                    style="width:50px"
                    size="sm"
                    :class="{ 'is-invalid': discussItSettingsForm.errors.has('min_number_of_initiate_or_reply_in_threads') }"
                    @keydown="discussItSettingsForm.errors.clear('min_number_of_initiate_or_reply_in_threads')"
                  />
                  <div class="col-form-label col-form-label-sm text-right pl-2">
                    {{
                      +discussItSettingsForm.min_number_of_initiate_or_reply_in_threads === 1
                        ? 'thread' : 'different threads'
                    }}
                    <QuestionCircleTooltip :id="'initiate-or-reply-tooltip'" />
                    <b-tooltip target="initiate-or-reply-tooltip" triggers="hover focus" delay="500">
                      If you enter a 0, this will not be considered in the
                      completion criteria.
                    </b-tooltip>
                  </div>
                </div>
              </b-form-group>
              <div>
                <ErrorMessage
                  :message="discussItSettingsForm.errors.get('min_number_of_initiate_or_reply_in_threads')"
                />
              </div>
              <b-form-group
                v-if="showSubmitAtLeastXComments"
                label-cols-sm="5"
                label-cols-lg="4"
                label-for="number_of_comments"
                label-size="sm"
                label-align="right"
              >
                <template #label>
                  Submit at least
                </template>
                <div class="flex d-inline-flex">
                  <div class="flex d-inline-flex">
                    <b-form-input
                      v-model="discussItSettingsForm.min_number_of_comments"
                      style="width:50px"
                      size="sm"
                      :class="{ 'is-invalid': discussItSettingsForm.errors.has('min_number_of_comments') }"
                      @keydown="discussItSettingsForm.errors.clear('min_number_of_comments')"
                    />
                    <div class="col-form-label col-form-label-sm text-right pl-2">
                      {{ +discussItSettingsForm.min_number_of_comments === 1 ? 'comment' : 'comments' }}
                    </div>
                  </div>
                </div>
                <QuestionCircleTooltip :id="'submit-comments-tooltip'" />
                <b-tooltip target="submit-comments-tooltip" triggers="hover focus" delay="500">
                  You or any of your students can submit comments. Each comment can be text or audio/video. Comments
                  are
                  then grouped
                  within discussion threads.
                  <br><br>If you enter a 0, this will not
                  be considered in the
                  completion criteria.
                </b-tooltip>
              </b-form-group>
              <ErrorMessage :message="discussItSettingsForm.errors.get('min_number_of_comments')" />
              <div class="pb-1 flex d-inline-flex col-form-label col-form-label-sm">
                <label for="response-modes" class="mr-2">Students may respond using:
                  <QuestionCircleTooltip
                    id="response-modes-tooltip"
                  />
                  <b-tooltip target="response-modes-tooltip"
                             delay="250"
                             triggers="hover focus"
                  >Choose the type of comments that students are allowed to make.
                  </b-tooltip>
                </label>
                <b-form-checkbox-group
                  id="response-modes"
                  v-model="discussItSettingsForm.response_modes"
                  :options="responseModeOptions"
                  class="mb-3"
                  value-field="item"
                  text-field="name"
                  @change="discussItSettingsForm.errors.clear('response_modes')"
                />
                <ErrorMessage :message="discussItSettingsForm.errors.get('response_modes')" />
              </div>
              <div v-show="getAudioVideoLabel()" class="pb-2 col-form-label col-form-label-sm">
                A comment will receive credit towards completion if:
              </div>
              <b-form-group
                v-show="discussItSettingsForm.response_modes && discussItSettingsForm.response_modes.includes('text')"
                label-cols-sm="5"
                label-cols-lg="4"
                label="Text is at least"
                label-size="sm"
                label-align="right"
              >
                <div style="width:115px">
                  <b-input-group
                    :append="+discussItSettingsForm.min_number_of_words === 1 ?'word' : 'words'"
                    size="sm"
                  >
                    <b-form-input v-model="discussItSettingsForm.min_number_of_words"
                                  :class="{ 'is-invalid': discussItSettingsForm.errors.has('min_number_of_words') }"
                                  @keydown="discussItSettingsForm.errors.clear('min_number_of_words')"
                    />
                  </b-input-group>
                </div>
                <ErrorMessage :message="discussItSettingsForm.errors.get('min_number_of_words')" />
              </b-form-group>
              <b-form-group
                v-show="getAudioVideoLabel()"
                label-cols-sm="5"
                label-cols-lg="4"
                label-for="audio_video_length"
                label-size="sm"
                label-align="right"
              >
                <template #label>
                  {{ getAudioVideoLabel() }} is at least
                </template>
                <b-form-input id="audio_video_length"
                              v-model="discussItSettingsForm.min_length_of_audio_video"
                              size="sm"
                              placeholder="Ex. 1 minute"
                              style="width:200px"
                              :class="{ 'is-invalid': discussItSettingsForm.errors.has('min_length_of_audio_video') }"
                              @keydown="discussItSettingsForm.errors.clear('min_length_of_audio_video')"
                />
                <ErrorMessage :message="discussItSettingsForm.errors.get('min_length_of_audio_video')" />
              </b-form-group>
            </b-card-text>
          </b-card>
        </div>
      </b-container>
      <template #modal-footer="{ ok }">
        <b-button size="sm"
                  @click="$bvModal.hide(`modal-discuss-it-settings`)"
        >
          Cancel
        </b-button>
        <b-button size="sm" variant="primary"
                  @click="saveDiscussItSettings"
        >
          Save
        </b-button>
      </template>
    </b-modal>
    <b-modal id="modal-confirm-paste-into-ckeditor"
             title="Pasted Content"
             no-close-on-esc
             no-close-on-backdrop
             hide-header-close
    >
      <p>
        You have just pasted content into the text discussion comment. We will tag your submission
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

    <b-container>
      <div v-if="loaded
        && !previewingQuestion && user.role === 2
        && discussItSettingsForm.response_modes
        && discussItSettingsForm.response_modes.length === 0"
      >
        <b-alert variant="warning" show>
          Currently your students cannot respond to this question because you have not chosen any response mode in
          your Discuss-it settings. Please choose whether your students can respond using text, audio, or video.
        </b-alert>
      </div>

      <div v-html="qtiJson.prompt" />
      <hr>
      <b-row>
        <b-col :cols="previewingQuestion ? 12 : 8" class="border border-dark" :class="previewingQuestion ? '' : 'mr-2'">
          <div class="mt-2">
            <b-pagination
              v-show="questionMediaUploads.length >1"
              id="discuss-it-pagination"
              v-model="currentMediaUploadOrder"
              :total-rows="questionMediaUploads.length"
              :per-page="1"
              pills
              align="center"
              first-number
              last-number
              limit="17"
              @change="updateDiscussions($event)"
            />
            <hr v-show="questionMediaUploads.length >1">
          </div>
          <div v-if="currentDiscussionMedia && /\.(mp3|mp4)$/.test(currentDiscussionMedia.s3_key)">
            <iframe
              :key="`discussion-media`"
              v-resize="{ log: false }"
              aria-label="Discussion Media"
              width="100%"
              allowtransparency="true"
              :src="`/question-media-player/${currentDiscussionMedia.s3_key}`"
              frameborder="0"
              allowfullscreen
              class="mb-2"
            />
          </div>
          <div v-if="currentDiscussionMedia && /\.(pdf)$/.test(currentDiscussionMedia.s3_key)">
            <VuePdfEmbed annotation-layer text-layer :source="currentDiscussionMedia.temporary_url" />
          </div>
          <div v-if="currentDiscussionMedia && currentDiscussionMedia.text"
               :class="!previewingQuestion ? 'ml-2 mr-2' : ''"
               v-html="currentDiscussionMedia.text"
          />
        </b-col>
        <b-col v-if="!previewingQuestion" class="border p-2 border-dark discuss-it-sticky-col" style="font-size:small">
          <div class="mb-2">
            <b-card v-if="+discussItSettingsForm.completion_criteria" header="default"
                    header-html="<h2 class='h7'>Submission Information</h2>"
            >
              <p>
                To count towards completion credit: {{
                  completionRequirementsToolTipText
                }}
              </p>
              <ul style="list-style: none;padding:0">
                <li
                  v-for="(completionRequirement,completionRequirementIndex) in completionRequirements.filter(item =>item.show)"
                  :key="`completion-requirement-${completionRequirementIndex}`"
                >
                  <CompletedIcon :completed="completionRequirement.requirement_satisfied" />
                  <span :class="completionRequirement.requirement_satisfied ? 'text-success' : 'text-danger'">
                    {{ completionRequirement.text }} <span
                    v-if="completionRequirement.key === 'min_number_of_initiated_discussion_threads'"
                  >   <QuestionCircleTooltip
                    id="min-number-of-initiated-discussion-threads-tooltip"
                  />
                      <b-tooltip target="min-number-of-initiated-discussion-threads-tooltip"
                                 delay="250"
                                 triggers="hover focus"
                      >

                        {{ numberOfInitiatedDiscussionThreadsMessage }}
                      </b-tooltip></span>
                    <span v-if="completionRequirement.key === 'min_number_of_replies'">   <QuestionCircleTooltip
                      id="min-number-of-replies-tooltip"
                    />
                      <b-tooltip target="min-number-of-replies-tooltip"
                                 delay="250"
                                 triggers="hover focus"
                      >
                        {{ numberOfRepliesMessage }}
                      </b-tooltip></span>
                    <span v-if="completionRequirement.key === 'min_number_of_initiate_or_reply_in_threads'">   <QuestionCircleTooltip
                      id="min-number-of-initiate-or-reply-in-threads-tooltip"
                    />
                      <b-tooltip target="min-number-of-initiate-or-reply-in-threads-tooltip"
                                 delay="250"
                                 triggers="hover focus"
                      >
                        {{ numberOfInitiateOrReplyInThreadsMessage }}
                      </b-tooltip></span>
                  </span>
                </li>
              </ul>
              <hr v-if="discussionCommentSubmissionResults.submission_summary">
              <ul v-if="discussionCommentSubmissionResults.submission_summary" style="list-style: none;padding-left:0">
                <li>
                  <span class="font-weight-bold">Date Completed:</span>
                  {{ discussionCommentSubmissionResults.submission_summary.date_submitted }}
                </li>
                <li>
                  <span class="font-weight-bold">Date Graded:</span>
                  {{ discussionCommentSubmissionResults.submission_summary.date_graded }}
                </li>
                <li>
                  <span class="font-weight-bold">Score:</span>
                  {{ discussionCommentSubmissionResults.submission_summary.score }}
                </li>
                <li v-if="discussionCommentSubmissionResults.submission_summary.text_feedback">
                  <span class="font-weight-bold">Feedback:</span>
                  <span v-html="discussionCommentSubmissionResults.submission_summary.text_feedback" />
                </li>
                <li v-if="discussionCommentSubmissionResults.submission_summary.file_feedback">
                  <strong>{{ discussionCommentSubmissionResults.submission_summary.file_feedback_type }} Feedback:
                    <a :href="discussionCommentSubmissionResults.submission_summary.file_feedback_url"
                       target="”_blank”"
                    >
                      {{
                        discussionCommentSubmissionResults.submission_summary.file_feedback_type === 'Audio' ? 'Listen To Feedback' : 'View Feedback'
                      }}
                    </a>
                  </strong>
                </li>
              </ul>
              <div
                v-if="discussionCommentSubmissionResults.submission_summary && discussionCommentSubmissionResults.submission_summary.grader_id"
                class="pr-2"
              >
                <hr>
                <b-button size="sm" variant="outline-primary"
                          @click="$emit('openContactGraderModal','discuss-it')"
                >
                  Contact Grader
                </b-button>
              </div>
            </b-card>
          </div>
          <hr v-if="discussions.length && canStartDiscussionOrAddComments && !previewingQuestion">
          <div v-if="canStartDiscussionOrAddComments && !previewingQuestion" class="mb-3">
            <span v-if="groupOptions.length > 1 && user.role === 2" class="mr-2">
              <b-form-select id="group"
                             v-model="group"
                             :options="groupOptions"
                             size="sm"
                             style="width:100px"
                             @change="updateGroup($event)"
              />
            </span>
            <b-button
              variant="success"
              size="sm"
              @click="startDiscussion"
            >
              New Thread
            </b-button>
            <span v-show="discussions.length > 1" class="float-right">
              <b-button variant="outline-info" size="sm" @click="showAllDiscussions">Show All</b-button> <b-button
              size="sm" @click="hideAllDiscussions"
            >Hide All</b-button>
            </span>
          </div>
          <div v-if="showComposePanel && !activeDiscussion.id"
               id="discuss-it-compose-panel-new-thread"
               class="discuss-it-compose-panel border rounded p-3 mb-3"
          >
            <div v-if="responseModeMissingError">
              <b-alert variant="info" show>
                The response mode (text, audio, video) for this Discuss-it question has not yet been set.
                <span v-show="user && user.role === 3"> Please contact your instructor.</span>
                <span v-show="user && user.role === 2"> Please add at least one response mode in your Discuss-it settings.</span>
              </b-alert>
            </div>
            <div v-if="!responseModeMissingError">
              <b-form-group>
                <b-form-radio-group
                  v-model="commentType"
                  stacked
                  required
                  label="Comment Type:"
                >
                  <b-form-radio v-show="showCommentTypeOption('text')" name="comment-type" value="text">
                    Text
                  </b-form-radio>
                  <b-form-radio v-show="showCommentTypeOption('audio')" name="comment-type"
                                value="audio"
                  >
                    Audio
                  </b-form-radio>
                  <b-form-radio v-show="showCommentTypeOption('video')" name="comment-type"
                                value="video"
                  >
                    Video
                  </b-form-radio>
                </b-form-radio-group>
              </b-form-group>
              <div v-if="commentType === 'text'">
                <DiscussItSatisfiesRequirement v-if="+discussItSettingsForm.completion_criteria"
                                               :min-number-of-words="+minNumberOfWords"
                                               :comment-form-text="commentForm.text"
                                               :comment-type="'text'"
                />
                <ckeditor
                  id="discuss_it_text"
                  ref="discuss_it_text"
                  v-model="commentForm.text"
                  tabindex="0"
                  required
                  :config="discussItEditorConfig"
                  :class="{ 'is-invalid': commentForm.errors.has('text')}"
                  class="mb-2"
                  @namespaceloaded="onCKEditorNamespaceLoaded"
                  @ready="handleFixCKEditorWithPasteWarning"
                  @keydown="commentForm.errors.clear('text')"
                />
              </div>
              <div v-if="commentType === 'audio'">
                <div v-if="!discussionCommentAudio">
                  <DiscussItSatisfiesRequirement v-if="+discussItSettingsForm.completion_criteria"
                                                 ref="discussItSatisfiesRequirement"
                                                 :key="`discussItSatisfiesRequirement-${discussItSatisfiesRequirementKey}`"
                                                 :milliseconds-time-until-requirement-satisfied="millisecondsTimeUntilRequirementSatisfied"
                                                 :human-readable-time-until-requirement-satisfied="humanReadableTimeUntilRequirementSatisfied"
                                                 :comment-type="'audio'"
                                                 :show-satisfies-requirement-timer="showSatisfiesRequirementTimer"
                                                 @setRequirementSatisfied="setRequirementSatisfied"
                  />
                  <DiscussItCommentAndSubmitWorkUpload :key="'new-audio'"
                                                       :comment-type="'audio'"
                                                       :assignment-id="assignmentId"
                                                       :question-id="questionId"
                                                       @saveUploadedAudioVideoComment="saveUploadedAudioVideoComment"
                  />
                  <div v-if="isPhone()">
                    <NativeAudioVideoRecorder :key="`new-comment-${commentType}`"
                                              :recording-type="'audio'"
                                              :assignment-id="+assignmentId"
                                              @saveComment="saveComment"
                                              @startVideoRecording="startVideoRecording"
                                              @stopVideoRecording="stopVideoRecording"
                                              @updateDiscussionCommentVideo="updateDiscussionCommentVideo"
                    />
                  </div>
                  <div v-else class="discuss-it-recorder-scroll">
                    <audio-recorder
                      id="discuss-it-recorder"
                      ref="recorder"
                      :upload-url="`/api/discussion-comments/assignment/${assignmentId}/question/${questionId}/audio`"
                      :attempts="1"
                      :time="3"
                      :show-download-button="false"
                      :after-recording="afterRecording"
                      :before-recording="beforeRecording"
                      :successful-upload="successfulRecordingUpload"
                      :failed-upload="failedRecordingUpload"
                      :mic-failed="micFailed"
                    />
                  </div>
                </div>
                <div v-if="discussionCommentAudio">
                  <iframe v-resize="{ log: false }"
                          :src="`/discussion-comments/media-player/discussion-comment-id/${activeDiscussionCommentId}/is-phone/${+this.isPhone()}`"
                          width="100%"
                          frameborder="0"
                          allowfullscreen
                  />
                  <div class="pb-4">
                    <b-button v-if="false"
                              class="float-right mr-2"
                              size="sm"
                              variant="danger"
                              @click="confirmDeleteComment('audio')"
                    >
                      Delete
                    </b-button>
                  </div>
                </div>
              </div>
              <div v-show="commentType === 'video'">
                <DiscussItSatisfiesRequirement
                  v-if="!discussionCommentVideo && Boolean(+discussItSettingsForm.completion_criteria)"
                  ref="discussItSatisfiesRequirement"
                  :key="`discussItSatisfiesRequirement-${discussItSatisfiesRequirementKey}`"
                  :milliseconds-time-until-requirement-satisfied="millisecondsTimeUntilRequirementSatisfied"
                  :human-readable-time-until-requirement-satisfied="humanReadableTimeUntilRequirementSatisfied"
                  :comment-type="commentType"
                  :show-satisfies-requirement-timer="showSatisfiesRequirementTimer"
                  @setRequirementSatisfied="setRequirementSatisfied"
                />
                <DiscussItCommentAndSubmitWorkUpload :key="'new-video'"
                                                     :comment-type="'video'"
                                                     :assignment-id="assignmentId"
                                                     :question-id="questionId"
                                                     @saveUploadedAudioVideoComment="saveUploadedAudioVideoComment"
                />
                <NativeAudioVideoRecorder :key="`new-comment-${commentType}`"
                                          :recording-type="'video'"
                                          :assignment-id="+assignmentId"
                                          @saveComment="saveComment"
                                          @startVideoRecording="startVideoRecording"
                                          @stopVideoRecording="stopVideoRecording"
                                          @updateDiscussionCommentVideo="updateDiscussionCommentVideo"
                />
              </div>
              <div class="mt-2">
                <span v-show="commentType === 'audio' && stoppedAudioRecording">
                  <span class="mr-2">
                    <b-button
                      variant="primary"
                      size="sm"
                      @click="saveAudio"
                    >
                      Save
                    </b-button>
                  </span>
                  <b-button
                    size="sm"
                    variant="outline-danger"
                    @click="reRecordAudio"
                  >
                    Delete and re-record
                  </b-button>
                </span>
                <span v-show="commentType === 'text'">
                  <b-button size="sm"
                            variant="primary"
                            @click="saveComment()"
                  >
                    Save
                  </b-button>
                </span>
                <b-button
                  size="sm"
                  class="float-right"
                  @click="closeComposePanel"
                >
                  Cancel
                </b-button>
              </div>
            </div>
          </div>
          <div v-if="discussions.length">
            <div class="accordion" role="tablist">
              <div class="clearfix mb-2" />
              <b-card v-for="(discussion, discussionIndex) in discussions" :key="`discussion-${discussionIndex}`"
                      no-body
                      class="mb-1"
              >
                <b-card-header header-tag="header" class="p-1" role="tab">
                  <b-button v-b-toggle="`accordion-${discussionIndex}`" block variant="info">
                    {{ discussion.started_by }} on
                    {{ discussion.created_at }} <span class="float-right"><b-icon-eye
                    v-show="!isOpen(discussionIndex)"
                  /> <b-icon-eye-slash v-show="isOpen(discussionIndex)" />
                    </span>
                  </b-button>
                </b-card-header>
                <b-collapse :id="`accordion-${discussionIndex}`"
                            role="tabpanel"
                            :visible="isOpen(discussionIndex)"
                            @shown="$set(openStates, discussionIndex, true)"
                            @hidden="$set(openStates, discussionIndex, false)"
                >
                  <b-card-body>
                    <b-card-text>
                      <div v-for="(comment, commentsIndex) in discussion.comments"
                           :key="`comments-${discussion.id}-${commentsIndex}`"
                      >
                        <user-initials
                          v-b-tooltip="{title: `${comment.created_by_name}`,delay: '500'}"
                          :user-name="comment.created_by_name"
                          :is-user="comment.created_by_user_id === user.id"
                          style="cursor: pointer"
                        />
                        <span class="text-muted">{{ comment.created_at }}</span>
                        <div v-show="comment.text" v-html="comment.text" />
                        <span v-show="comment.file">
                          <b-button size="sm" variant="outline-info" @click="listenOrViewComment(comment)">{{ listenOrViewCommentText(comment) }}</b-button>
                        </span>
                        <a :id="getTooltipTarget('editComment',comment.id)"
                           href=""
                           :aria-label="`Edit comment by ${ comment.created_by_name }, created on ${comment.created_at }`"
                           @click.prevent="initEditComment(comment)"
                        >
                          <span v-if="showAction('editComment',comment.created_by_user_id)"><b-icon-pencil
                            class="font-weight-bold"
                          /></span>
                        </a>
                        <a :id="getTooltipTarget('deleteComment',comment.id)"
                           href=""
                           :aria-label="`Delete comment by ${ comment.created_by_name }, created on ${comment.created_at }`"
                           @click.prevent="initDeleteComment(comment)"
                        >
                          <span v-if="showAction('deleteComment',comment.created_by_user_id)"><b-icon-trash
                            class="font-weight-bold"
                          /></span>
                        </a>
                        <b-tooltip :target="getTooltipTarget('deleteComment',comment.id)"
                                   delay="500"
                                   triggers="hover"
                        >
                          Delete comment by {{ comment.user_id === user.id ? 'you' : comment.created_by_name }}, created
                          on {{ comment.created_at }}
                        </b-tooltip>
                        <hr>
                      </div>
                      <b-button v-if="canStartDiscussionOrAddComments"
                                size="sm"
                                class="mb-3"
                                @click="newComment(discussion)"
                      >
                        New Comment
                      </b-button>
                      <div v-if="showComposePanel && activeDiscussion.id === discussion.id"
                           :id="`discuss-it-compose-panel-${discussion.id}`"
                           class="discuss-it-compose-panel border rounded p-3 mb-3"
                      >
                        <div v-if="responseModeMissingError">
                          <b-alert variant="info" show>
                            The response mode (text, audio, video) for this Discuss-it question has not yet been set.
                            <span v-show="user && user.role === 3"> Please contact your instructor.</span>
                            <span v-show="user && user.role === 2"> Please add at least one response mode in your Discuss-it settings.</span>
                          </b-alert>
                        </div>
                        <div v-if="!responseModeMissingError">
                          <b-form-group>
                            <b-form-radio-group
                              v-model="commentType"
                              stacked
                              required
                              label="Comment Type:"
                            >
                              <b-form-radio v-show="showCommentTypeOption('text')" name="comment-type" value="text">
                                Text
                              </b-form-radio>
                              <b-form-radio v-show="showCommentTypeOption('audio')" name="comment-type"
                                            value="audio"
                              >
                                Audio
                              </b-form-radio>
                              <b-form-radio v-show="showCommentTypeOption('video')" name="comment-type"
                                            value="video"
                              >
                                Video
                              </b-form-radio>
                            </b-form-radio-group>
                          </b-form-group>
                          <div v-if="commentType === 'text'">
                            <DiscussItSatisfiesRequirement v-if="+discussItSettingsForm.completion_criteria"
                                                           :min-number-of-words="+minNumberOfWords"
                                                           :comment-form-text="commentForm.text"
                                                           :comment-type="'text'"
                            />
                            <ckeditor
                              id="discuss_it_text"
                              ref="discuss_it_text"
                              v-model="commentForm.text"
                              tabindex="0"
                              required
                              :config="discussItEditorConfig"
                              :class="{ 'is-invalid': commentForm.errors.has('text')}"
                              class="mb-2"
                              @namespaceloaded="onCKEditorNamespaceLoaded"
                              @ready="handleFixCKEditorWithPasteWarning"
                              @keydown="commentForm.errors.clear('text')"
                            />
                          </div>
                          <div v-if="commentType === 'audio'">
                            <div v-if="!discussionCommentAudio">
                              <DiscussItSatisfiesRequirement v-if="+discussItSettingsForm.completion_criteria"
                                                             ref="discussItSatisfiesRequirement"
                                                             :key="`discussItSatisfiesRequirement-${discussItSatisfiesRequirementKey}`"
                                                             :milliseconds-time-until-requirement-satisfied="millisecondsTimeUntilRequirementSatisfied"
                                                             :human-readable-time-until-requirement-satisfied="humanReadableTimeUntilRequirementSatisfied"
                                                             :comment-type="'audio'"
                                                             :show-satisfies-requirement-timer="showSatisfiesRequirementTimer"
                                                             @setRequirementSatisfied="setRequirementSatisfied"
                              />
                              <DiscussItCommentAndSubmitWorkUpload :key="'new-audio'"
                                                                   :comment-type="'audio'"
                                                                   :assignment-id="assignmentId"
                                                                   :question-id="questionId"
                                                                   @saveUploadedAudioVideoComment="saveUploadedAudioVideoComment"
                              />
                              <div v-if="isPhone()">
                                <NativeAudioVideoRecorder :key="`new-comment-${commentType}`"
                                                          :recording-type="'audio'"
                                                          :assignment-id="+assignmentId"
                                                          @saveComment="saveComment"
                                                          @startVideoRecording="startVideoRecording"
                                                          @stopVideoRecording="stopVideoRecording"
                                                          @updateDiscussionCommentVideo="updateDiscussionCommentVideo"
                                />
                              </div>
                              <div v-else class="discuss-it-recorder-scroll">
                                <audio-recorder
                                  id="discuss-it-recorder"
                                  ref="recorder"
                                  :upload-url="`/api/discussion-comments/assignment/${assignmentId}/question/${questionId}/audio`"
                                  :attempts="1"
                                  :time="3"
                                  :show-download-button="false"
                                  :after-recording="afterRecording"
                                  :before-recording="beforeRecording"
                                  :successful-upload="successfulRecordingUpload"
                                  :failed-upload="failedRecordingUpload"
                                  :mic-failed="micFailed"
                                />
                              </div>
                            </div>
                            <div v-if="discussionCommentAudio">
                              <iframe v-resize="{ log: false }"
                                      :src="`/discussion-comments/media-player/discussion-comment-id/${activeDiscussionCommentId}/is-phone/${+this.isPhone()}`"
                                      width="100%"
                                      frameborder="0"
                                      allowfullscreen
                              />
                              <div class="pb-4">
                                <b-button v-if="false"
                                          class="float-right mr-2"
                                          size="sm"
                                          variant="danger"
                                          @click="confirmDeleteComment('audio')"
                                >
                                  Delete
                                </b-button>
                              </div>
                            </div>
                          </div>
                          <div v-show="commentType === 'video'">
                            <DiscussItSatisfiesRequirement
                              v-if="!discussionCommentVideo && Boolean(+discussItSettingsForm.completion_criteria)"
                              ref="discussItSatisfiesRequirement"
                              :key="`discussItSatisfiesRequirement-${discussItSatisfiesRequirementKey}`"
                              :milliseconds-time-until-requirement-satisfied="millisecondsTimeUntilRequirementSatisfied"
                              :human-readable-time-until-requirement-satisfied="humanReadableTimeUntilRequirementSatisfied"
                              :comment-type="commentType"
                              :show-satisfies-requirement-timer="showSatisfiesRequirementTimer"
                              @setRequirementSatisfied="setRequirementSatisfied"
                            />
                            <DiscussItCommentAndSubmitWorkUpload :key="'new-video'"
                                                                 :comment-type="'video'"
                                                                 :assignment-id="assignmentId"
                                                                 :question-id="questionId"
                                                                 @saveUploadedAudioVideoComment="saveUploadedAudioVideoComment"
                            />
                            <NativeAudioVideoRecorder :key="`new-comment-${commentType}`"
                                                      :recording-type="'video'"
                                                      :assignment-id="+assignmentId"
                                                      @saveComment="saveComment"
                                                      @startVideoRecording="startVideoRecording"
                                                      @stopVideoRecording="stopVideoRecording"
                                                      @updateDiscussionCommentVideo="updateDiscussionCommentVideo"
                            />
                          </div>
                          <div class="mt-2">
                            <span v-show="commentType === 'audio' && stoppedAudioRecording">
                              <span class="mr-2">
                                <b-button
                                  variant="primary"
                                  size="sm"
                                  @click="saveAudio"
                                >
                                  Save
                                </b-button>
                              </span>
                              <b-button
                                size="sm"
                                variant="outline-danger"
                                @click="reRecordAudio"
                              >
                                Delete and re-record
                              </b-button>
                            </span>
                            <span v-show="commentType === 'text'">
                              <b-button size="sm"
                                        variant="primary"
                                        @click="saveComment()"
                              >
                                Save
                              </b-button>
                            </span>
                            <b-button
                              size="sm"
                              class="float-right"
                              @click="closeComposePanel"
                            >
                              Cancel
                            </b-button>
                          </div>
                        </div>
                      </div>
                    </b-card-text>
                  </b-card-body>
                </b-collapse>
              </b-card>
            </div>
          </div>
        </b-col>
      </b-row>
    </b-container>
  </div>
</template>

<script>
import VuePdfEmbed from 'vue-pdf-embed/dist/vue2-pdf-embed'
import axios from 'axios'
import { mapGetters } from 'vuex'
import Form from 'vform'
import AllFormErrors from '../AllFormErrors.vue'
import ErrorMessage from '../ErrorMessage.vue'
import { getTooltipTarget } from '../../helpers/Tooptips'
import UserInitials from '../UserInitials.vue'
import CompletedIcon from '../CompletedIcon.vue'
import DiscussItSatisfiesRequirement from '../DiscussItSatisfiesRequirement.vue'
import { updateModalToggleIndex } from '~/helpers/accessibility/fixCKEditor'
import CKEditor from 'ckeditor4-vue'
import Transcript from '../Transcript.vue'
import DiscussItCommentAndSubmitWorkUpload from '../DiscussItCommentAndSubmitWorkUpload.vue'
import NativeAudioVideoRecorder from '../NativeAudioVideoRecorder.vue'
import { isPhone } from '~/helpers/isPhone'
import { handleFixCKEditorWithPasteWarning } from '~/helpers/ckeditor.js'

export default {
  name: 'DiscussItViewer',
  components: {
    NativeAudioVideoRecorder,
    DiscussItCommentAndSubmitWorkUpload,
    Transcript,
    DiscussItSatisfiesRequirement,
    CompletedIcon,
    ErrorMessage,
    AllFormErrors,
    UserInitials,
    VuePdfEmbed,
    ckeditor: CKEditor.component
  },
  props: {
    previewingQuestion: {
      type: Boolean,
      default: false
    },
    qtiJson: {
      type: Object,
      default: () => {
      }
    },
    assignmentId: {
      type: Number,
      default: 0
    },
    questionId: {
      type: Number,
      default: 0
    },
    canStartDiscussionOrAddComments: {
      type: Boolean,
      default: true
    }
  },
  data: () => ({
      showComposePanel: false,
      pastedContent: true,
      loaded: false,
      responseModeMissingError: false,
      showSubmitAtLeastXComments: false,
      numberOfInitiatedDiscussionThreadsMessage: '',
      numberOfRepliesMessage: '',
      numberOfInitiateOrReplyInThreadsMessage: '',
      group: 1,
      groupOptions: [{ value: 1, text: 'Group 1' }],
      languageOptions: [
        { text: 'Choose a Language', value: null },
        { text: 'English', value: 'en' },
        { text: 'French', value: 'fr' },
        { text: 'Spanish', value: 'es' },
        { text: 'German', value: 'de' },
        { text: 'Mandarin Chinese', value: 'zh' },
        { text: 'Arabic', value: 'ar' },
        { text: 'Italian', value: 'it' },
        { text: 'Russian', value: 'ru' },
        { text: 'Portuguese', value: 'pt' },
        { text: 'Japanese', value: 'ja' },
        { text: 'Hindi', value: 'hi' },
        { text: 'Bengali', value: 'bn' },
        { text: 'Lahnda (Punjabi)', value: 'pa' },
        { value: '--------------', text: '--------------------', disabled: true },
        { text: 'Multiple', value: 'multiple' }
      ],
      responseModeOptions: [
        { item: 'text', name: 'Text' },
        { item: 'audio', name: 'Audio' },
        { item: 'video', name: 'Video' }],
      listenOrViewCommentKey: 0,
      currentFile: '',
      fileRequirementSatisfied: false,
      alreadyScoredWarning: '',
      discussionCommentsExist: false,
      millisecondsTimeUntilRequirementSatisfied: 0,
      humanReadableTimeUntilRequirementSatisfied: '',
      discussItEditorConfig: {
        toolbar: [
          { name: 'image', items: ['Image'] },
          { name: 'math', items: ['Mathjax'] }
        ],
        // Configure the Enhanced Image plugin to use classes instead of styles and to disable the
        // resizer (because image size is controlled by widget styles or the image takes maximum
        // 100% of the editor width).
        image2_alignClasses: ['image-align-left', 'image-align-center', 'image-align-right'],
        image2_altRequired: true,
        removeButtons: '',
        extraPlugins: 'mathjax,embed,dialog,contextmenu,liststyle,image2,autogrow',
        mathJaxLib: 'https://cdnjs.cloudflare.com/ajax/libs/mathjax/2.7.4/MathJax.js?config=TeX-AMS_HTML',
        embed_provider: '//ckeditor.iframe.ly/api/oembed?url={url}&callback={callback}',
        filebrowserUploadUrl: '/api/ckeditor/upload',
        filebrowserUploadMethod: 'form',
        format_tags: 'p;h2;h3;pre',
        allowedContent: true,
        disableNativeSpellChecker: false
      },
      questionMediaUploads: [],
      discussionCommentSubmissionResultsError: '',
      openStates: [true],
      openIndex: 0,
      discussionCommentVideo: false,
      discussItSatisfiesRequirementKey: 0,
      showSatisfiesRequirementTimer: false,
      minNumberOfWords: 0,
      discussionCommentSubmissionResults: { min_number_of_discussion_threads: '' },
      numberOfThreadsParticipatedInMessage: '',
      numberOfCommentsSubmittedMessage: '',
      completionRequirementsToolTipText: '',
      hasCompletionRequirements: false,
      currentDiscussionMedia: {},
      reRecording: false,
      confirmDeleteCommentText: '',
      discussItSettingsForm: new Form({
        response_modes: [],
        language: null,
        min_number_of_comments: '',
        students_can_edit_comments: '',
        students_can_delete_comments: ''
      }),
      activeDiscussionComment: {},
      activeDiscussionCommentId: 0,
      audioHeaders: {},
      discussionCommentAudio: false,
      stoppedAudioRecording: false,
      showPDF: true,
      activeUserId: 0,
      discussionsByUserId: [],
      activeDiscussion: {},
      allFormErrors: [],
      commentForm: new Form({
        text: '',
        commentType: 'text'
      }),
      commentType: 'text',
      discussions: [],
      currentMediaUploadOrder: 1,
      numPages: 0,
      completionRequirements: []
    }
  ),
  computed: {
    ...mapGetters({
      user: 'auth/user'
    })
  },
  watch: {
    'discussItSettingsForm.auto_grade': {
      handler (newValue) {
        this.$forceUpdate()
        if (+newValue === 1 && +this.discussItSettingsForm.completion_criteria !== 1) {
          this.$noty.info('Auto-graded questions must have a completion criteria associated with it.')
          this.$nextTick(() =>
            this.discussItSettingsForm.completion_criteria = 1
          )
        }
      },
      deep: true
    },
    'discussItSettingsForm.completion_criteria': {
      handler (newValue) {
        if (+newValue === 0 && +this.discussItSettingsForm.auto_grade === 1) {
          this.$noty.info('Auto-graded questions must have a completion criteria associated with it.')
          this.$nextTick(() =>
            this.discussItSettingsForm.completion_criteria = 1
          )
        }
      },
      deep: true
    }
  },
  async mounted () {
    if (this.assignmentId && this.questionId) {
      if (this.user.role === 2) {
        this.group = 1
      } else {
        await this.getDiscussionGroupByAssignmentIdQuestionIdUserId()
      }
      const questionMediaUploads = await this.getMediaUploadsByQuestionId()
      if (questionMediaUploads !== false) {
        this.questionMediaUploads = questionMediaUploads
        await this.$nextTick(() => {
          this.updateDiscussions(1)
          this.getDiscussItSettings()
        })
      }
    } else {
      if (this.qtiJson) {
        if (this.previewingQuestion) {
          if (this.qtiJson.media_uploads) {
            await this.getTemporaryUrls(this.qtiJson.media_uploads)
          } else {
            this.questionMediaUploads = this.qtiJson.question_media_uploads
          }
        }
      }
    }
  },
  methods: {
    handleFixCKEditorWithPasteWarning,
    isPhone,
    updateModalToggleIndex,
    keepPastedContent () {
      this.pastedContent = true
      this.$bvModal.hide('modal-confirm-paste-into-ckeditor')
    },
    startAgain () {
      this.$bvModal.hide('modal-confirm-paste-into-ckeditor')
      this.closeComposePanel()
      this.$bvModal.hide('update-text-comment')
      this.pastedContent = false
    },
    closeComposePanel () {
      this.showComposePanel = false
    },
    async updateGroup (group) {
      this.group = group
      await this.getDiscussionsByMediaUploadId()
    },
    async getDiscussionGroupByAssignmentIdQuestionIdUserId () {
      try {
        const { data } = await axios.get(`/api/discussion-groups/assignment/${this.assignmentId}/question/${this.questionId}`)
        if (data.type === 'error') {
          this.$noty.error(data.message)
        }
        this.group = data.group
      } catch (error) {
        this.$noty.error(error.message)
      }
    },
    updateNumberOfGroups () {
      this.groupOptions = [{ value: 1, text: 'Group 1' }]
      const numberOfGroups = +this.discussItSettingsForm.number_of_groups
      if (numberOfGroups > 1) {
        for (let i = 2; i <= numberOfGroups; i++) {
          this.groupOptions.push({ value: i, text: `Group ${i}` })
        }
      }
    },
    removeCurrentTranscript () {
      this.activeDiscussionComment.transcript = ''
      this.activeDiscussionComment.re_processed_transcript = 1
      this.activeTranscript = []
    },
    downloadTranscript () {
      document.getElementById('download-transcript').click()
    },
    saveUploadedAudioVideoComment (file, fileRequirementSatisfied) {
      this.fileRequirementSatisfied = fileRequirementSatisfied
      this.saveComment(file)
    },
    getTooltipTarget,
    showCommentTypeOption (option) {
      return this.discussItSettingsForm.response_modes.includes(option) && this.discussItSettingsForm.response_modes.length > 1
    },
    getAudioVideoLabel () {
      let label
      label = ''
      if (this.discussItSettingsForm.response_modes.length) {
        if (this.discussItSettingsForm.response_modes.includes('audio') && this.discussItSettingsForm.response_modes.includes('video')) {
          label = 'Audio/Video'
        } else if (this.discussItSettingsForm.response_modes.includes('audio')) {
          label = 'Audio'
        } else if (this.discussItSettingsForm.response_modes.includes('video')) {
          label = 'Video'
        }
      }
      return label
    },
    async hideMediaModal () {
      this.$bvModal.hide('modal-listen-or-view-comment')
      await this.getDiscussionsByMediaUploadId(false)
    },
    async getTemporaryUrls (questionMediaUploads) {
      if (questionMediaUploads[0]) {
        try {
          const { data } = await axios.patch(`/api/question-media/temporary-urls`, { question_media_uploads: questionMediaUploads })
          if (data.type === 'error') {
            this.$noty.error(data.message)
          } else {
            this.questionMediaUploads = data.question_media_uploads
          }
        } catch (error) {
          this.$noty.error(error.message)
        }
        this.currentDiscussionMedia = this.questionMediaUploads[0]
      }
    },
    setRequirementSatisfied () {
      this.fileRequirementSatisfied = true
    },
    onCKEditorNamespaceLoaded (CKEDITOR) {
      CKEDITOR.addCss('.cke_editable { font-size: 15px; }')
    },
    async getMediaUploadsByQuestionId () {
      try {
        const { data } = await axios.get(`/api/question-media/assignments/${this.assignmentId}/questions/${this.questionId}`)
        if (data.type === 'error') {
          this.$noty.error(data.message)
          return false
        }
        return data.question_media_uploads
      } catch (error) {
        this.$noty.error(error.message)
      }
    },
    showAllDiscussions () {
      this.openStates = this.openStates.map(() => true)
    },
    hideAllDiscussions () {
      this.openStates = this.openStates.map(() => false)
    },
    isOpen (index) {
      return this.openStates[index] || false
    },
    updateDiscussionCommentVideo () {
      this.discussionCommentVideo = true
    },
    stopVideoRecording () {
      this.$refs.discussItSatisfiesRequirement.endCountDown()
    },
    startVideoRecording () {
      this.discussionCommentVideo = false
      this.showSatisfiesRequirementTimer = true
    },
    async getSatisfiedRequirements () {
      try {
        const { data } = await axios.get(`/api/discussion-comments/assignment/${this.assignmentId}/question/${this.questionId}/user/${this.user.id}/satisfied`)
        if (data.type === 'error') {
          this.$noty.error(data.message)
        } else {
          const satisfiedRequirements = data.satisfied_requirements
          if (this.completionRequirements.find(item => item.key === 'min_number_of_initiated_discussion_threads')) {
            this.completionRequirements.find(item => item.key === 'min_number_of_initiated_discussion_threads').requirement_satisfied = satisfiedRequirements.satisfied_min_number_of_initiated_discussion_threads_requirement
            this.numberOfInitiatedDiscussionThreadsMessage = satisfiedRequirements.number_of_initiated_discussion_threads_message
          }
          if (this.completionRequirements.find(item => item.key === 'min_number_of_replies')) {
            this.completionRequirements.find(item => item.key === 'min_number_of_replies').requirement_satisfied = satisfiedRequirements.satisfied_min_number_of_replies_requirement
            this.numberOfRepliesMessage = satisfiedRequirements.number_of_replies_message
          }
          if (this.completionRequirements.find(item => item.key === 'min_number_of_initiate_or_reply_in_threads')) {
            this.completionRequirements.find(item => item.key === 'min_number_of_initiate_or_reply_in_threads').requirement_satisfied = satisfiedRequirements.satisfied_min_number_of_initiate_or_reply_in_threads_requirement
            this.numberOfInitiateOrReplyInThreadsMessage = satisfiedRequirements.number_of_initiate_or_reply_in_threads_message
          }
          this.discussionCommentSubmissionResults = satisfiedRequirements
        }
      } catch (error) {
        this.$noty.error(error.message)
      }
    },
    initEditComment (comment) {
      this.activeDiscussionComment = comment
      if (comment.text) {
        this.commentForm.text = comment.text
        this.commentForm.type = 'text'
        this.$bvModal.show('modal-update-text-comment')
      } else {
        this.listenOrViewComment(comment)
      }
    },
    async deleteComment () {
      try {
        const { data } = await axios.delete(`/api/discussion-comments/${this.activeDiscussionComment.id}`)
        this.$noty[data.type](data.message)
        if (data.type !== 'error') {
          this.$bvModal.hide('modal-confirm-delete-comment')
          this.activeDiscussionComment = {}
          await this.getDiscussionsByMediaUploadId()
          await this.getSatisfiedRequirements()
        }
      } catch (error) {
        this.$noty.error(error.message)
      }
    },
    listenOrViewCommentText (comment) {
      if (comment.file) {
        return comment.recording_type === 'audio' ? 'Listen to Comment' : 'View Video'
      } else {
        return ''
      }
    },
    async initDeleteComment (discussionComment) {
      this.activeDiscussionComment = discussionComment
      this.alreadyScoredWarning = ''
      const canDeleteComment = !await this.deletingWillMakeRequirementsNotSatisfied(discussionComment)
      if (canDeleteComment) {
        this.confirmDeleteCommentText = discussionComment.created_by_user_id === this.user.id
          ? `you created on ${this.activeDiscussionComment.created_at}`
          : `created by ${this.activeDiscussionComment.created_by_name} at ${this.activeDiscussionComment.created_at}`
        this.$bvModal.show('modal-confirm-delete-comment')
      } else {
        if (this.user.role === 3) {
          this.$bvModal.show('modal-cannot-delete-comment')
        } else {
          this.alreadyScoredWarning = 'This question has already been automatically scored. After the deleting this comment, this student\'s submission will no longer satisfy the scoring criteria. You may optionally adjust the score in the Open Grader.'
          this.$bvModal.show('modal-confirm-delete-comment')
        }
      }
    },
    async deletingWillMakeRequirementsNotSatisfied (discussionComment) {
      try {
        const { data } = await axios.get(`/api/discussion-comments/${discussionComment.id}/deleting-will-make-requirements-not-satisfied`)
        if (data.type === 'error') {
          this.$noty.error(data.message)
          return false
        }
        return data.deleting_will_make_requirements_not_satisfied
      } catch (error) {
        this.$noty.error(error.message)
      }
      return false
    },
    showAction (action, userId) {
      let showAction
      showAction = false
      switch (action) {
        case ('editComment'):
          showAction = (this.canStartDiscussionOrAddComments && this.user.role === 3 && userId === this.user.id && this.discussItSettingsForm.students_can_edit_comments) ||
            this.user.role === 2
          break
        case ('deleteComment'):
          showAction = (this.canStartDiscussionOrAddComments && this.user.role === 3 && userId === this.user.id && this.discussItSettingsForm.students_can_delete_comments) ||
            this.user.role === 2
          break
      }
      return showAction
    },
    initDiscussItSettings () {
      this.discussItSettingsForm.errors.clear()
      this.getDiscussItSettings()
    },
    async getDiscussItSettings () {
      try {
        const { data } = await axios.get(`/api/assignments/${this.assignmentId}/question/${this.questionId}/discuss-it-settings`)
        if (data.type === 'success') {
          this.discussionCommentsExist = data.discussion_comments_exist
          this.discussItSettingsForm = new Form(JSON.parse(data.discuss_it_settings))
          if (this.user.role === 2) {
            this.updateNumberOfGroups()
          }
          if (!this.discussItSettingsForm.language) {
            this.discussItSettingsForm.language = null
          }
          this.showSubmitAtLeastXComments = data.show_submit_at_least_x_comments
          this.humanReadableTimeUntilRequirementSatisfied = this.discussItSettingsForm.min_length_of_audio_video
          this.millisecondsTimeUntilRequirementSatisfied = this.discussItSettingsForm.min_length_of_audio_video_in_milliseconds
          this.completionRequirements = [
            { key: 'min_number_of_initiated_discussion_threads', requirement_satisfied: false, show: true },
            { key: 'min_number_of_replies', requirement_satisfied: false, show: true },
            { key: 'min_number_of_initiate_or_reply_in_threads', requirement_satisfied: false, show: true },
            {
              key: 'min_number_of_comments',
              requirement_satisfied: false,
              show: this.discussItSettingsForm.hasOwnProperty('min_number_of_comments')
            },
            { key: 'min_number_of_words', show: false },
            { key: 'min_length_of_audio_video', show: false }
          ]
          this.completionRequirementsToolTipText = ''
          if (+this.discussItSettingsForm.completion_criteria) {
            for (let i = 0; i < this.completionRequirements.length; i++) {
              const key = this.completionRequirements[i].key
              const requirementExists = this.discussItSettingsForm[key] !== '' && +this.discussItSettingsForm[key] !== 0
              this.completionRequirements[i].requirementExists = requirementExists
              if (requirementExists) {
                const value = this.discussItSettingsForm[key]
                const plural = +value === 1 ? '' : 's'
                switch (key) {
                  case ('min_number_of_initiated_discussion_threads'):
                    this.completionRequirements[i].text = `Initiate ${value} discussion thread${plural} with new comment(s).`
                    break
                  case ('min_number_of_replies'):
                    const reply = plural ? 'replies' : 'reply'
                    this.completionRequirements[i].text = `Submit ${value} ${reply} to comments in existing threads.`
                    break
                  case ('min_number_of_initiate_or_reply_in_threads'):
                    const sAtEnd = plural ? 's' : ''
                    this.completionRequirements[i].text = `Participate (initiate or reply) in ${value} thread${sAtEnd}.`
                    break
                  case ('min_number_of_comments'):
                    this.completionRequirements[i].text = `Submit at least ${value} comment${plural}.`
                    break
                  case ('min_number_of_words'):
                    if (this.discussItSettingsForm && this.discussItSettingsForm.response_modes.includes('text')) {
                      this.completionRequirementsToolTipText += `Text comments need to be at least ${value} word${plural}.  `
                    }
                    this.minNumberOfWords = value
                    break
                  case ('min_length_of_audio_video'):
                    const audioVideoLabel = this.getAudioVideoLabel()
                    if (audioVideoLabel) {
                      this.completionRequirementsToolTipText += `${audioVideoLabel} comments need to be at least ${value}.`
                    }
                    break
                }
              }
            }
            this.completionRequirements = this.completionRequirements.filter(item => item.requirementExists)
            await this.getSatisfiedRequirements()
          }
        } else {
          this.$noty.error(data.message)
        }
      } catch (error) {
        this.$noty.error(error.message)
      }
      this.loaded = true
    },
    async saveDiscussItSettings () {
      try {
        const { data } = await this.discussItSettingsForm.patch(`/api/assignments/${this.assignmentId}/question/${this.questionId}/discuss-it-settings`)
        this.$noty[data.type](data.message)
        if (data.type === 'success') {
          this.$bvModal.hide('modal-discuss-it-settings')
        }
        await this.getDiscussItSettings()
      } catch (error) {
        if (!error.message.includes('status code 422')) {
          this.$noty.error(error.message)
        } else {
          this.allFormErrors = this.discussItSettingsForm.errors.flatten()
          this.$bvModal.show('modal-form-errors-discussion-settings')
        }
      }
    },
    async updateTranscriptInMedia () {
      const activeComment = this.activeDiscussionComment
      await this.getDiscussionsByMediaUploadId(false)
      this.listenOrViewCommentKey++
      this.activeDiscussionComment = activeComment
    },
    listenOrViewComment (comment) {
      this.activeDiscussionComment = comment
      this.fileRequirementSatisfied = false
      this.commentType = comment.recording_type
      this.$bvModal.show('modal-listen-or-view-comment')
    },
    confirmDeleteComment (test) {

    },
    async saveAudio () {
      if (!document.getElementsByClassName('ar__uploader')[0]) {
        this.$noty.info('Please first record some audio by clicking on the mic icon and then clicking the stop icon when you have completed your recording.')
      } else {
        document.getElementsByClassName('ar__uploader')[0].click()
      }
    },
    reRecordAudio () {
      this.discussionCommentAudio = false
      this.discussItSatisfiesRequirementKey++
      this.showSatisfiesRequirementTimer = false
      try {
        this.$refs.recorder.removeRecord()
      } catch (error) {
        console.log('Does not exist if done multiple times.')
      }
      this.stoppedAudioRecording = false
      this.$noty.info('The recording has been deleted.')
    },
    afterRecording () {
      this.$nextTick(() => {
        document.getElementsByClassName('ar-records__record')[0].click()
      })
      this.stoppedAudioRecording = true
      this.$refs.discussItSatisfiesRequirement.endCountDown()
    },
    beforeRecording () {
      this.discussionCommentAudio = false
      this.showSatisfiesRequirementTimer = true
    },
    successfulRecordingUpload (response) {
      const data = response.data
      data.type === 'success' ? this.saveComment(data.file) : this.$noty.error(data.message)
    },
    failedRecordingUpload (response) {
      this.$noty[response.data.type](response.data.message)
    },
    micFailed () {
      this.$noty.error('We are unable to access your mic.')
    },
    updateDiscussions (currentMediaUploadOrder) {
      this.currentDiscussionMedia = this.questionMediaUploads.find(mediaUpload => mediaUpload.order === currentMediaUploadOrder)
      if (!this.previewingQuestion) {
        this.$nextTick(() => {
          this.getDiscussionsByMediaUploadId()
        })
      }
    },
    openComposePanel () {
      this.showComposePanel = true
      this.currentFile = ''
      this.$nextTick(() => {
        updateModalToggleIndex('discuss-it-compose-panel')
        const panel = document.getElementById('discuss-it-compose-panel')
        if (panel) {
          panel.scrollIntoView({ behavior: 'smooth', block: 'nearest' })
        }
      })
    },
    initCommentOrDiscussion (discussion = {}) {
      this.responseModeMissingError = false
      if (!this.discussItSettingsForm.response_modes.length) {
        this.responseModeMissingError = true
        this.openComposePanel()
        return
      }
      if (this.discussItSettingsForm.response_modes.includes('text')) {
        this.commentType = 'text'
        this.commentForm = new Form({
          text: ''
        })
      } else if (this.discussItSettingsForm.response_modes.includes('audio')) {
        this.commentType = 'audio'
      } else {
        this.commentType = 'video'
      }
      this.activeDiscussion = {}
      this.activeDiscussionComment = {}
      this.discussionCommentAudio = false
      this.discussionCommentVideo = false
      this.discussItSatisfiesRequirementKey++
      this.showSatisfiesRequirementTimer = false
      this.fileRequirementSatisfied = false

      this.activeDiscussion = discussion.id ? discussion : {}
      this.openComposePanel()
    },
    startDiscussion () {
      this.initCommentOrDiscussion()
    },
    newComment (discussion) {
      this.initCommentOrDiscussion(discussion)
    },
    getMediaUploadId () {
      return this.questionMediaUploads.find(mediaUpload => mediaUpload.order === this.currentMediaUploadOrder).id
    },
    async saveComment (file = '', recordingType = '') {
      if (this.currentFile) {
        // hack to stop the audio recorder from double recording
        return
      } else {
        this.currentFile = file
      }
      this.reRecording = false
      const mediaUploadId = this.getMediaUploadId()
      const discussionId = this.activeDiscussion.id ? this.activeDiscussion.id : 0
      const action = this.activeDiscussionComment.id ? 'patch' : 'post'
      const group = this.user.role === 2 ? this.group : 0 // server side check for students
      const url = this.activeDiscussionComment.id
        ? `/api/discussion-comments/${this.activeDiscussionComment.id}`
        : `/api/discussions/assignment/${this.assignmentId}/question/${this.questionId}/${mediaUploadId}/${discussionId}/${group}`
      this.commentForm.type = this.commentType
      if (this.commentForm.type === 'text') {
        this.commentForm.pasted_comment = +this.pastedContent
      }
      if (file || ['audio', 'video'].includes(this.commentType)) {
        if (file) {
          if (file.endsWith('.mp3')) {
            recordingType = 'audio'
          }
          if (file.endsWith('.mp4')) {
            recordingType = 'video'
          }
        }
        this.commentForm.file = file
        this.commentForm.type = file ? 'file' : this.commentType
        this.commentForm.recording_type = recordingType
        this.commentForm.file_requirement_satisfied = this.fileRequirementSatisfied
      }

      try {
        await this.getDiscussionsByMediaUploadId(false)
        this.closeComposePanel()
        this.$bvModal.hide('modal-update-text-comment')
        this.$bvModal.hide('modal-listen-or-view-comment')
        const { data } = await this.commentForm[action](url)
        this.activeDiscussionCommentId = data.discussion_comment_id
        if (data.type !== 'error') {
          await this.getSatisfiedRequirements()
          this.$bvModal.show('modal-discussion-comment-submission-accepted')
          await this.getDiscussionsByMediaUploadId()
        } else {
          this.discussionCommentSubmissionResultsError = data.message
          this.$bvModal.show('modal-discussion-comment-submission-not-accepted')
        }
      } catch (error) {
        if (!error.message.includes('status code 422')) {
          this.$noty.error(error.message)
        } else {
          this.allFormErrors = this.commentForm.errors.flatten()
          this.$bvModal.show('modal-form-errors-comments')
        }
      }
    },
    async getDiscussionsByMediaUploadId (resetOpenStates = true) {
      const mediaUploadId = this.getMediaUploadId()
      try {
        const { data } = await axios.get(`/api/discussions/assignment/${this.assignmentId}/question/${this.questionId}/media-upload/${mediaUploadId}`)
        if (['error', 'info'].includes(data.type)) {
          this.$noty[data.type](data.message)
          return false
        }
        this.discussions = data.discussions.filter(item => item.group === this.group)

        this.typesetMath()
        if (resetOpenStates) {
          for (let i = 0; i < this.discussions.length; i++) {
            this.openStates[i] = i === 0
          }
        }
      } catch (error) {
        this.$noty.error(error.message)
      }
    }
  }
}
</script>
<style scoped>
.discuss-it-sticky-col {
  /* Keeps the discussion/compose column in view while the person scrolls
     through a long attachment on the left. It stays pinned near the top
     of the viewport as the page scrolls, and lets go once the bottom of
     the row (i.e. the end of the attachment column) reaches that point.
     max-height + overflow-y give it its own scrollbar in the rare case
     where the column's own content is taller than the viewport. */
  position: sticky;
  top: 1rem;
  max-height: calc(100vh - 2rem);
  overflow-y: auto;
}

.discuss-it-compose-panel {
  background-color: #fff;
  max-height: 70vh;
  overflow-y: auto;
}

.discuss-it-recorder-scroll {
  /* vue-audio-recorder is built at a fixed width (.ar is 420px, its
     .ar-player child is 380px, etc.) with no responsive sizing at all.
     `zoom` shrinks the whole widget - visuals *and* the layout space it
     reserves - together, so nothing overflows or gets clipped. Fall back
     to horizontal scroll for the rare pre-2024 browser that ignores zoom. */
  overflow-x: auto;
  padding-bottom: 4px;
}

.discuss-it-recorder-scroll #discuss-it-recorder {
  zoom: 0.7;
}
</style>
