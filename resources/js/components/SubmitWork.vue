<template>
  <div>
    <AllFormErrors :all-form-errors="allFormErrors" :modal-id="'modal-form-errors-submit-work'"/>
    <b-modal id="modal-confirm-delete-submitted-work"
             title="Confirm Delete Work"
             size="md"
    >
      Are you sure that you would like to delete your submitted work? Note that your auto-graded submission will remain
      unaffected
      by this action.
      <template #modal-footer>
        <b-button
          size="sm"
          class="float-right"
          @click="$bvModal.hide('modal-confirm-delete-submitted-work')"
        >
          Cancel
        </b-button>
        <b-button
          size="sm"
          variant="danger"
          class="float-right"
          @click="deleteSubmittedWork"
        >
          Delete
        </b-button>
      </template>
    </b-modal>
    <span id="submit-work" class="d-inline-block" tabindex="0">
      <b-button
        v-show="!loading"
        size="sm"
        :disabled="disabled"
        :variant="submittedWorkUrl ? 'success' : 'danger'"
        @click="initShowSubmitWorkModal()"
      >Submit<span v-show="submittedWorkUrl">ted</span> Work <span v-show="!submittedWorkUrl"
      >({{ optional ? 'optional' : 'required' }})</span>
      </b-button>
    </span>
    <b-modal id="modal-submit-work"
             title="Submitted Work"
             size="lg"
             @hidden="updateSubmittedWork"
    >
      <div v-if="currentSubmittedWorkAt">
        <strong>Last Submitted:</strong> {{ currentSubmittedWorkAt }}
      </div>
      <div v-if="timeLeftToSubmit">
        <b-alert variant="info" :show="!submitButtonActive">
          You may no longer submit supporting work.
        </b-alert>
        <div v-if="submitButtonActive">
          <b-form-group>
            <b-form-radio-group
              v-show="submittedWorkFormatOptions.length > 1"
              v-model="chosenSubmittedWorkFormat"
              inline
              required
            >
              <label class="mr-2">Format:</label>
              <b-form-radio v-show="showSubmittedWorkFormatOption('file')" name="comment-type"
                            value="file"
              >
                File
              </b-form-radio>
              <b-form-radio v-show="showSubmittedWorkFormatOption('audio')" name="comment-type"
                            value="audio"
              >
                Audio
              </b-form-radio>
              <b-form-radio v-show="showSubmittedWorkFormatOption('video')" name="comment-type"
                            value="video"
              >
                Video
              </b-form-radio>
            </b-form-radio-group>
          </b-form-group>
          <div v-if="chosenSubmittedWorkFormat === 'file'">
            <file-upload
              ref="submittedWorkFiles"
              v-model="submittedWorkFiles"
              class="btn btn-outline-primary btn-sm"
              accept=".pdf,.jpeg,.jpg,.png,.heic,.gif,.webp"
              put-action="/put.method"
              @input-file="inputFile"
              @input-filter="inputFilter"
            >
              Select a PDF or image
            </file-upload>
          </div>
          <b-row class="upload mt-3 ml-1">
            <div v-if="submittedWorkFiles.length && (preSignedURL !== '')">
              <div v-for="file in submittedWorkFiles" :key="file.id">
                File to upload:
                <span :class="file.success ? 'text-success font-weight-bold' : ''">{{
                    file.name
                  }}</span> -
                <span>{{ formatFileSize(file.size) }} </span>
                <b-button
                  v-if="(preSignedURL !== '')"
                  variant="info"
                  size="sm"
                  style="vertical-align: top"
                  @click.prevent="$refs.submittedWorkFiles.active = true"
                >
                  Upload
                </b-button>
                <span v-else-if="file.active" class="ml-2 text-info">
                <b-spinner small type="grow"/>
                Uploading File...
              </span>
                <div v-if="file.error" class="text-danger">
                  Error: {{ file.error }}
                </div>
              </div>
            </div>
          </b-row>
        </div>
        <div v-if="chosenSubmittedWorkFormat === 'audio'">
          <div v-if="!submittedWorkUrl || (submittedWorkFormat !== 'audio')">
            <DiscussItCommentAndSubmitWorkUpload v-if="reRecording"
                                                 :key="'re-record-audio'"
                                                 :comment-type="'audio'"
                                                 :submission-type="'submit-work'"
                                                 :assignment-id="assignmentId"
                                                 :question-id="questionId"
                                                 @saveUploadedAudioVideoSubmittedWork="saveUploadAudioVideoSubmittedWork"
            />
            <div v-if="isPhone()">
              <NativeAudioVideoRecorder v-if="reRecording"
                                        :upload-type="'submitted-work'"
                                        :key="`new-submit-work-${chosenSubmittedWorkFormat}`"
                                        :recording-type="'audio'"
                                        :assignment-id="+assignmentId"
                                        @saveComment="storeSubmittedWork"
              />
            </div>
            <div v-else>
              <audio-recorder
                v-if="reRecording"
                id="discuss-it-recorder"
                ref="recorder"
                :upload-url="`/api/submitted-work/assignments/${assignmentId}/questions/${questionId}/audio`"
                :attempts="1"
                :time="3"
                class="m-auto"
                :show-download-button="false"
                :after-recording="afterRecording"
                :successful-upload="successfulRecordingUpload"
                :failed-upload="failedRecordingUpload"
                :mic-failed="micFailed"
              />
            </div>
          </div>
        </div>
        <div v-if="chosenSubmittedWorkFormat === 'video' && submittedWorkFormat !== 'video'">
          <DiscussItCommentAndSubmitWorkUpload v-if="reRecording"
                                               :key="'re-record-video'"
                                               :comment-type="'video'"
                                               :submission-type="'submit-work'"
                                               :assignment-id="assignmentId"
                                               :question-id="questionId"
                                               @saveUploadedAudioVideoSubmittedWork="saveUploadAudioVideoSubmittedWork"
          />
          <NativeAudioVideoRecorder v-if="reRecording"
                                    :upload-type="'submitted-work'"
                                    key="update-video-submit-work"
                                    :recording-type="'video'"
                                    :assignment-id="+assignmentId"
                                    @storeSubmittedWork="storeSubmittedWork"
          />
        </div>
        <b-embed
          v-if="submittedWorkUrl && (submittedWorkFormat === chosenSubmittedWorkFormat)"
          :key="submittedWorkUrl"
          v-resize="{ log: false, checkOrigin: false }"
          width="100%"
          :src="submittedWorkUrl"
          allowfullscreen
        />
      </div>
      <div v-else>
        <div v-if="submittedWorkUrl">
          <b-embed
            v-if="submittedWorkUrl"
            :key="submittedWorkUrl"
            v-resize="{ log: false, checkOrigin: false }"
            width="100%"
            :src="submittedWorkUrl"
            allowfullscreen
          />
        </div>
        <div v-else>
          <b-alert show variant="info">
            You have not submitted any work and the assignment is now closed.
          </b-alert>
        </div>
      </div>

      <template #modal-footer>
          <span class="mr-2">
            <b-button
              v-show="chosenSubmittedWorkFormat === 'audio' && stoppedAudioRecording"
              variant="primary"
              size="sm"
              @click="saveAudio"
            >
              Save
            </b-button>
          </span>
        <b-button v-if="submittedWorkUrl && submittedWorkFormat === chosenSubmittedWorkFormat"
                  variant="danger"
                  size="sm"
                  @click="$bvModal.show('modal-confirm-delete-submitted-work')"
        >
          Delete
        </b-button>
        <b-button
          size="sm"
          class="float-right"
          @click="$bvModal.hide('modal-submit-work')"
        >
          Cancel
        </b-button>
      </template>
    </b-modal>
  </div>
</template>

<script>
import { getAcceptedFileTypes, submitUploadFile, formatFileSize } from '~/helpers/UploadFiles'
import axios from 'axios'
import { fixInvalid } from '../helpers/accessibility/FixInvalid'
import Vue from 'vue'
import AllFormErrors from './AllFormErrors.vue'
import Form from 'vform'
import NativeAudioVideoRecorder from './NativeAudioVideoRecorder.vue'
import { isPhone } from '~/helpers/isPhone'
import DiscussItCommentAndSubmitWorkUpload from './DiscussItCommentAndSubmitWorkUpload.vue'

const VueUploadComponent = require('vue-upload-component')
Vue.component('file-upload', VueUploadComponent)
export default {
  name: 'SubmitWork',
  components: {
    DiscussItCommentAndSubmitWorkUpload,
    NativeAudioVideoRecorder,
    AllFormErrors,
    FileUpload: VueUploadComponent
  },
  props: {
    timeLeftToSubmit: {
      type: Boolean,
      default: false
    },
    optional: {
      type: Boolean,
      default: false
    },
    submittedWorkFormatOptions: {
      type: Array,
      default: function () {
        return []
      }
    },
    disabled: {
      type: Boolean,
      default:
        false
    },
    submittedWork: {
      type: String,
      default:
        null
    },
    submittedWorkAt: {
      type: String,
      default:
        null
    },
    assignmentId: {
      type: Number,
      default:
        0
    },
    questionId: {
      type: Number,
      default:
        0
    },
    userId: {
      type: Number,
      default:
        0
    },
    submitButtonActive: {
      type: Boolean,
      default:
        false
    }

  },
  data: () => ({
    loading: true,
    stoppedAudioRecording: false,
    submittedWorkFormat: '',
    chosenSubmittedWorkFormat: '',
    reRecording: false,
    submittedWorkForm: new Form({
      assignment_id: null,
      question_id: null
    }),
    submittedWorkFiles: [],
    progress: 0,
    preSignedURL: '',
    allFormErrors: [],
    handledOK: false,
    submittedWorkUrl: '',
    currentSubmittedWorkAt: null
  }),
  watch: {
    chosenSubmittedWorkFormat (newValue) {
      this.reRecording = ['audio', 'video'].includes(newValue)
    }
  },
  async mounted () {
    this.loading = true
    this.submittedWorkForm.assignmentId = this.assignmentId
    this.submittedWorkForm.questionId = this.questionId
    await this.getSubmittedWork(this.assignmentId, this.questionId)
    await this.updateSubmittedWork()
    if (this.submittedWorkFormatOptions !== [] && !this.submittedWorkUrl) {
      this.chosenSubmittedWorkFormat = this.submittedWorkFormatOptions[0]
    }
    this.loading = false
  },
  methods: {
    isPhone,
    getAcceptedFileTypes,
    submitUploadFile,
    formatFileSize,
    async getSubmittedWork (assignmentId, questionId) {
      const { data } = await axios.get(`/api/submitted-work/assignments/${assignmentId}/questions/${questionId}`)
      if (data.type !== 'success') {
        this.$noty.error(data.message)
        return false
      }
      if (data.submitted_work) {
        const submittedWork = data.submitted_work
        this.submittedWorkUrl = submittedWork.submitted_work
        this.submittedWorkFormat = this.chosenSubmittedWorkFormat = submittedWork.format
        this.currentSubmittedWorkAt = submittedWork.submitted_work_at
      }
    },
    showSubmittedWorkFormatOption (submittedWorkFormat) {
      return this.submittedWorkFormatOptions.includes(submittedWorkFormat)
    },
    async saveAudio () {
      if (!document.getElementsByClassName('ar__uploader')[0]) {
        this.$noty.info('Please first record some audio by clicking on the mic icon and then clicking the stop icon when you have completed your recording.')
      } else {
        document.getElementsByClassName('ar__uploader')[0].click()
      }
    },
    afterRecording () {
      this.$nextTick(() => {
        document.getElementsByClassName('ar-records__record')[0].click()
      })
      this.stoppedAudioRecording = true
    },
    successfulRecordingUpload (response) {
      const data = response.data
      data.type === 'success' ? this.storeSubmittedWork(data.file) : this.$noty.error(data.message)
    },
    failedRecordingUpload (response) {
      this.$noty[response.data.type](response.data.message)
    },
    micFailed () {
      this.$noty.error('We are unable to access your mic.')
    },
    updateSubmittedWork () {
      this.$emit('updateSubmittedWork', {
        submittedWorkUrl: this.submittedWorkUrl,
        submittedWorkAt: this.currentSubmittedWorkAt
      })
    },
    async saveUploadAudioVideoSubmittedWork (s3Key) {
      this.s3Key = s3Key
      await this.handleOK()
    },
    async storeSubmittedWork (file) {
      this.s3Key = `submitted-work/${this.assignmentId}/${file}`
      await this.handleOK()
    },
    async deleteSubmittedWork () {
      try {
        const { data } = await axios.delete(`/api/submitted-work/assignments/${this.assignmentId}/questions/${this.questionId}`)
        this.$noty[data.type](data.message)
        if (data.type === 'info') {
          this.submittedWorkUrl = null
          this.currentSubmittedWorkAt = null
          this.stoppedAudioRecording = false
          this.reRecording = true
          this.submittedWorkFormat = ''
          try {
            this.$refs.recorder.removeRecord()
          } catch (error) {
            console.log('Does not exist if done multiple times.')
          }
          this.$bvModal.hide('modal-confirm-delete-submitted-work')
        }
      } catch (error) {
        this.$noty.error(error.message)
      }
    },
    initSubmitWork () {
      this.submittedWorkFiles = []
      this.preSignedURL = ''
      this.handledOK = false
    },
    initShowSubmitWorkModal () {
      this.initSubmitWork()
      this.submittedWorkFiles = []
      this.preSignedURL = ''
      this.handledOK = false
      this.$bvModal.show('modal-submit-work')
    },
    inputFile (newFile, oldFile) {
      if (newFile && oldFile && !newFile.active && oldFile.active) {
        // Get response data
        this.progress = newFile ? newFile.progress : 0
        if (newFile.xhr) {
          //  Get the response status code
          // console.log('status', newFile.xhr.status)
          if (newFile.xhr.status === 200) {
            if (!this.handledOK) {
              this.handledOK = true
              // console.log(this.handledOK)
              this.handleOK()
            }
          } else {
            this.$noty.error('We were not able to save your file to our server.  Please try again or contact us if the problem persists.')
          }
        } else {
          this.$noty.error('We were not able to save your file to our server.  Please try again or contact us if the problem persists.')
        }
      }
    },
    async handleOK () {
      try {
        const { data } = await axios.patch(`/api/submitted-work/assignments/${this.assignmentId}/questions/${this.questionId}`,
          {
            submitted_work: this.s3Key,
            submitted_work_format: this.chosenSubmittedWorkFormat
          })
        this.$noty[data.type](data.message)

        if (data.type === 'success') {
          this.submittedWorkUrl = data.submitted_work_url
          this.currentSubmittedWorkAt = data.submitted_work_at
          this.submittedWorkFormat = this.chosenSubmittedWorkFormat
          // The recording is saved and the recorder is now hidden, so hide the Save button;
          // otherwise clicking it again shows "Please first record some audio..."
          this.stoppedAudioRecording = false
        }
      } catch (error) {
        this.$noty.error(error.message)
      }
      this.initSubmitWork()
    },
    async inputFilter (newFile, oldFile, prevent) {
      this.submittedWorkForm.errors.clear()
      if (newFile && !oldFile) {
        // Filter non-image file
        if (parseInt(newFile.size) > 20000000) {
          let message = '20 MB max allowed.  Your file is too large.  '
          this.submittedWorkForm.errors.set(this.uploadFileType, message)

          this.$nextTick(() => fixInvalid())
          this.allFormErrors = this.submittedWorkForm.errors.flatten()
          this.$bvModal.show('modal-form-errors-file-upload')

          return prevent()
        }
        let validUploadTypesMessage = `The valid upload types are: .pdf,.jpeg,.jpg,.png,.heic,.gif,.webp`
        const validExtension = /\.(pdf|jpeg|jpg|png|heic|gif|webp)$/i.test(newFile.name)

        if (!validExtension) {
          this.submittedWorkForm.errors.set(this.uploadFileType, validUploadTypesMessage)
          this.$nextTick(() => fixInvalid())
          this.allFormErrors = this.submittedWorkForm.errors.flatten()
          this.$bvModal.show('modal-form-errors-submit-work')
          return prevent()
        } else {
          try {
            this.preSignedURL = ''
            let uploadFileData = {
              assignment_id: this.assignmentId,
              question_id: this.questionId,
              upload_file_type: 'submitted-work',
              file_name: newFile.name
            }
            const { data } = await axios.post('/api/s3/pre-signed-url', uploadFileData)
            if (data.type === 'error') {
              this.$noty.error(data.message)
              return false
            }
            this.preSignedURL = data.preSignedURL
            newFile.putAction = this.preSignedURL

            this.s3Key = data.s3_key
            this.originalFilename = newFile.name
            this.handledOK = false
          } catch (error) {
            this.$noty.error(error.message)
            return false
          }
        }
      }

      // Create a blob field
      newFile.blob = ''
      let URL = window.URL || window.webkitURL
      if (URL && URL.createObjectURL) {
        newFile.blob = URL.createObjectURL(newFile.file)
      }
    }
  }
}
</script>
