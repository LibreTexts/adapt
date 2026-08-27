<template>
  <div>
    <AllFormErrors :all-form-errors="allFormErrors" :modal-id="`modal-form-errors-file-upload`"/>
    <b-container>
      <div class="mt-3 ml-1">
        <b-button size="sm" variant="info" @click="$bvModal.show(instructionsModalId)">
          Instructions
        </b-button>
      </div>
      <b-modal :id="instructionsModalId" title="Instructions" ok-only ok-title="Close" button-size="sm">
        <div v-show="commentType === 'audio'">
          <span v-show="isPhone()">
Use your phone to record and upload your {{ submissionType === 'discuss-it' ? 'audio comment' : 'submitted work' }} directly to ADAPT.
          </span><span v-show="!isPhone()">
          Use the built-in "ADAPT recorder" below to record and upload your {{
            submissionType === 'discuss-it' ? 'audio comment' : 'submitted work'
          }} directly to
          ADAPT.
            Otherwise, you may record your audio as an .mp3 file with another program (outside of
            ADAPT),
              save the .mp3 file to your computer, then</span></div>
        <div v-show="commentType === 'video'">Use your webcam to record and upload your
          {{ submissionType === 'discuss-it' ? 'video comment' : 'submitted work' }} directly to
          ADAPT.<span v-show="!isPhone()">
            Otherwise, you may record your {{ submissionType === 'discuss-it' ? 'video comment' : 'submitted work' }} as an .mp4 file with another program (outside of
            ADAPT),
            save the .mp4 file to your computer, then</span>
        </div>
        <span v-show="!isPhone()">
          <file-upload
            ref="componentFileUploader"
            v-model="files"
            class="btn btn-outline-primary btn-sm"
            :accept="commentType === 'audio' ? '.mp3' : '.mp4'"
            put-action="/put.method"
            @input-file="inputFile"
            @input-filter="inputUploadFileFilter"
          >
            upload the {{ commentType === 'audio' ? '.mp3' : '.mp4' }} file
          </file-upload>
          from your computer into ADAPT.
        </span>
      </b-modal>
      <b-row class="upload mt-3 ml-1">
        <div v-if="files.length && (preSignedURL !== '')">
          <div v-for="file in files" :key="file.id">
            File to upload:
            <span :class="file.success ? 'text-success font-weight-bold' : ''">{{
                file.name
              }}</span>
            <b-button
              v-if="(preSignedURL !== '')"
              variant="info"
              size="sm"
              style="vertical-align: top"
              :disabled="disableStartUpload"
              @click.prevent="initStartUpload()"
            >
              Upload
            </b-button>
            <span v-else-if="file.active" class="ml-2 text-info">
              <b-spinner small type="grow"/>
              Uploading File...
            </span>
            <b-button size="sm"
                      style="vertical-align: top"
                      :disabled="disableStartUpload"
                      @click.prevent="cancelUpload()"
            >
              Cancel
            </b-button>
            <div v-if="file.error" class="text-danger">
              Error: {{ file.error }}
            </div>
          </div>
        </div>
      </b-row>
      <b-progress v-if="preSignedURL" max="100" class="mt-2 mb-3">
        <b-progress-bar :value="progress" :label="`${Number(progress).toFixed(0)}%`" show-progress animated/>
      </b-progress>
      <b-row v-show="uploadFileErrorMessage" class="mb-3">
        <ErrorMessage :message="uploadFileErrorMessage"/>
      </b-row>
    </b-container>
  </div>
</template>

<script>
import ErrorMessage from './ErrorMessage.vue'
import axios from 'axios'
import { fixInvalid } from '../helpers/accessibility/FixInvalid'
import AllFormErrors from './AllFormErrors.vue'
import { isPhone } from '../helpers/isPhone'

export default {
  name: 'DiscussItCommentAndSubmitWorkUpload',
  components: { AllFormErrors, ErrorMessage },
  props: {
    submissionType: {
      type: String,
      default: 'discuss-it'
    },
    commentType: {
      type: String,
      default: ''
    },
    assignmentId: {
      type: Number,
      default: 0
    },
    questionId: {
      type: Number,
      default: 0
    }
  },
  data: () => ({
    allFormErrors: [],
    componentFileUploader: {},
    progress: 0,
    uploadFileErrorMessage: '',
    disableStartUpload: false,
    files: [],
    preSignedURL: '',
    modalId: '',
    activeMedia: {},
    activeTranscript: [],
    orderedMediaUploads: []
  }),
  computed: {
    instructionsModalId () {
      // Multiple instances of this component can be mounted at once
      // (e.g. the audio and video sections of the Discuss-it compose
      // panel), so the modal id must be unique per instance, not just
      // per comment-type, or $bvModal.show() opens every matching id.
      return `modal-discuss-it-instructions-${this.commentType}-${this._uid}`
    }
  },
  methods: {
    isPhone,
    initStartUpload () {
      this.allFormErrors = []
      this.$refs.componentFileUploader.active = true
    },
    async inputUploadFileFilter (newFile, oldFile, prevent) {
      this.uploadFileErrorMessage = ''
      this.errorMessages = []
      if (newFile && !oldFile) {
        if (parseInt(newFile.size) > 80000000) {
          this.uploadFileErrorMessage = '80 MB max allowed.  Your file is too large.'
          this.$nextTick(() => fixInvalid())
          this.allFormErrors = [this.uploadFileErrorMessage]
          this.$bvModal.show(`modal-form-errors-file-upload`)
          return prevent()
        }
        const acceptedExtensionsRegex = /\.(mp3|mp4)$/i
        const validExtension = acceptedExtensionsRegex.test(newFile.name)
        if (!validExtension) {
          this.uploadFileErrorMessage = `${newFile.name} does not have a valid extension.`
          this.$nextTick(() => fixInvalid())
          this.allFormErrors = [this.uploadFileErrorMessage]
          this.$bvModal.show(`modal-form-errors-file-upload`)
          return prevent()
        } else {
          try {
            this.preSignedURL = ''
            let uploadFileData = {
              upload_file_type: this.submissionType === 'discuss-it' ? 'discuss-it-comments' : 'submitted-work',
              file_name: newFile.name,
              assignment_id: this.assignmentId
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
            newFile.discuss_it_comments_filename = data.discuss_it_comments_filename
            newFile.temporary_url = data.temporary_url
            this.handledOK = false
          } catch (error) {
            this.$noty.error(error.message)
            return false
          }
        }
      }

      // Create a blob field
      this.progress = 0
      newFile.blob = ''
      let URL = window.URL || window.webkitURL
      if (URL && URL.createObjectURL) {
        newFile.blob = URL.createObjectURL(newFile.file)
      }
      console.log(newFile.blob)
    },
    inputFile (newFile, oldFile) {
      console.log(newFile)
      this.progress = newFile ? newFile.progress : 0

      if (newFile && oldFile && !newFile.active && oldFile.active) {
        // Get response data
        if (newFile.xhr) {
          //  Get the response status code
          console.log('status', newFile.xhr.status)
          if (newFile.xhr.status === 200) {
            if (!this.handledOK) {
              this.handledOK = true
              console.log(this.handledOK)
              console.log(newFile)
              this.disableStartUpload = true
              this.handleOK(newFile)
            }
          } else {
            this.$noty.error('We were not able to save your file to our server.  Please try again or contact us if the problem persists.')
          }
        } else {
          this.$noty.error('We were not able to save your file to our server.  Please try again or contact us if the problem persists.')
        }
      }
    },
    async handleOK (newFile) {
      this.preSignedURL = ''
      this.disableStartUpload = false
      switch (this.submissionType) {
        case ('discuss-it'):
          const fileRequirementSatisfied = await this.fileRequirementSatisfied(newFile.discuss_it_comments_filename)
          this.$emit('saveUploadedAudioVideoComment', newFile.discuss_it_comments_filename, fileRequirementSatisfied)
          break
        case ('submit-work'):
          this.$emit('saveUploadedAudioVideoSubmittedWork', this.s3Key)
          break
        default:
          alert(`${this.submissionType} is not a valid submission type for audio/video upload.`)
      }
    },
    async fileRequirementSatisfied (discussItCommentsFilename) {
      try {
        const { data } = await axios.patch(`/api/discussion-comments/assignment/${this.assignmentId}/question/${this.questionId}/audio-video-satisfied-file-requirements`,
          { file: discussItCommentsFilename })
        if (data.type === 'success') {
          return data.file_requirement_satisfied
        }
        this.$noty.error(data.message)
        return false
      } catch (error) {
        this.$noty.error(error.message)
        return false
      }
    },
    cancelUpload () {
      this.files = []
      this.preSignedURL = ''
      this.progress = 0
      this.$noty.info('The upload has been canceled.')
    }
  }
}
</script>

<style scoped>

</style>
