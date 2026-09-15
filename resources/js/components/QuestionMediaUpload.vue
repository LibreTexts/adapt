<template>
  <b-container>
    <AllFormErrors :all-form-errors="allFormErrors" :modal-id="`modal-form-errors-file-upload-${modalId}`"/>
    <b-modal id="modal-upload-vtt"
             title="Upload Transcript"
             no-close-on-backdrop
             no-close-on-esc
             size="lg"
             hide-footer
             @shown="isVttUpload = true"
             @hidden="isVttUpload = false"
    >
      <p>
        If you would like to upload your own .vtt file to use as the transcript, please select it from your
        computer.
      </p>
      <div v-if="isVttUpload">
        <file-upload
          ref="vttUpload"
          v-model="files2"
          class="btn btn-primary btn-sm"
          accept=".vtt"
          put-action="/put.method"
          @input-file="inputFile"
          @input-filter="inputUploadFileFilter"
        >
          Select .vtt file
        </file-upload>
        <b-row class="upload mt-3 ml-1">
          <div v-if="files2.length && (preSignedURL !== '')">
            <div v-for="file in files2" :key="file.id">
              File to upload:
              <span :class="file.success ? 'text-success font-weight-bold' : ''">{{
                  file.name
                }}</span> -
              <span>{{ formatFileSize(file.size) }} </span>
              <b-button
                v-if="(preSignedURL !== '') && (!$refs.vttUpload || !$refs.vttUpload.active)"
                variant="info"
                size="sm"
                style="vertical-align: top"
                :disabled="disableStartUpload"
                @click.prevent="initStartUpload('vttUpload')"
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
                        @click.prevent="cancelUpload"
              >
                Cancel
              </b-button>
              <div v-if="file.error" class="text-danger">
                Error: {{ file.error }}
              </div>
            </div>
          </div>
        </b-row>
        <b-progress v-if="preSignedURL && isVttUpload" max="100" class="mt-2 mb-3">
          <b-progress-bar :value="progress" :label="`${Number(progress).toFixed(0)}%`" show-progress animated/>
        </b-progress>
      </div>
    </b-modal>
    <b-modal id="modal-confirm-delete-question-media"
             :title="`Delete ${activeMedia.original_filename}`"
             no-close-on-esc
             no-close-on-backdrop
    >
      <p>Please confirm that you would like to delete:</p>
      <p class="text-center">
        {{ activeMedia.original_filename }}
      </p>
      <b-alert variant="danger" show>
        This action cannot be undone.
      </b-alert>
      <template #modal-footer>
        <b-button
          size="sm"
          class="float-right"
          @click="$bvModal.hide('modal-confirm-delete-question-media')"
        >
          Cancel
        </b-button>
        <b-button
          variant="danger"
          size="sm"
          class="float-right"
          @click="deleteQuestionMedia()"
        >
          Delete
        </b-button>
      </template>
    </b-modal>

    <b-modal id="modal-question-media"
             title="Question Media"
             size="xl"
             no-close-on-esc
             no-close-on-backdrop
             hide-footer
    >
      <b-row>
        <div :style="{
               width: (isVideo(activeMedia) && sizeWidth) ? sizeWidth + 'px' : '60%',
               maxWidth: '100%',
               margin: 'auto',
               padding: '0 15px'
             }"
        >
          <iframe
            :key="`question-media-${questionMediaKey}`"
            v-resize="{ log: false }"
            aria-label="Question Media"
            width="100%"
            allowtransparency="true"
            :src="`/question-media-player/${activeMedia.s3_key}/${startTime}`"
            frameborder="0"
            allowfullscreen
            class="mb-2"
          />
        </div>
      </b-row>
      <div v-if="isVideo(activeMedia)"
           class="mb-3 mt-2 p-2"
           style="border: 1px solid #dee2e6; border-radius: 6px; max-width: 60%; margin: 0 auto;"
      >
        <h6 class="mb-2">
          Video Size
        </h6>
        <p class="text-muted mb-2" style="font-size: 0.85em">
          By default the video expands to fill the available width in the question. Set a width to
          constrain it instead — it will never display larger than this, but will still shrink to fit
          on narrow screens.
        </p>
        <b-form-checkbox v-model="sizeMaintainAspectRatio" class="mb-2">
          Maintain aspect ratio
        </b-form-checkbox>
        <b-form-row>
          <b-col>
            <b-form-group label="Width (px)" label-for="media-size-width">
              <b-form-input id="media-size-width"
                            v-model.number="sizeWidth"
                            type="number"
                            min="1"
                            @input="onSizeWidthInput"
              />
            </b-form-group>
          </b-col>
          <b-col>
            <b-form-group label="Height (px)" label-for="media-size-height">
              <b-form-input id="media-size-height"
                            v-model.number="sizeHeight"
                            type="number"
                            min="1"
                            @input="onSizeHeightInput"
              />
            </b-form-group>
          </b-col>
        </b-form-row>
        <b-button size="sm" class="mr-2" @click="resetMediaSize">
          Reset to Fill
        </b-button>
        <b-button size="sm" variant="primary" @click="saveMediaSize">
          Apply Size
        </b-button>
      </div>
      <div v-if="needsTranscript(activeMedia)">
        <Transcript :model="'QuestionMediaUpload'"
                    :active-transcript="activeTranscript"
                    :active-media="activeMedia"
                    @hideMediaModal="hideMediaModal"
                    @updateTranscriptInMedia="updateTranscriptInMedia"
        />
      </div>
    </b-modal>
    <b-row>
      <div v-if="!isVttUpload" :class="isDiscussIt ? 'mt-3' : ''">
        <div class="d-inline-block">
          <file-upload
            ref="questionMediaUpload"
            v-model="files"
            class="btn btn-primary btn-sm"
            :accept="isDiscussIt ? '.mp3,.mp4,.pdf' : '.mp3,.mp4'"
            put-action="/put.method"
            @input-file="inputFile"
            @input-filter="inputUploadFileFilter"
          >
            {{ isDiscussIt ? 'Upload Media' : 'Upload Audio or Video' }}
          </file-upload>
          <QuestionCircleTooltipModal v-show="!isDiscussIt" :modal-id="'modal-upload-question-media'"/>
          <div v-if="isDiscussIt" class="text-muted mt-1" style="font-size: 0.8em;">
            MP3 · MP4 · PDF
          </div>
          <div v-else class="text-muted mt-1" style="font-size: 0.8em;">
            MP3 · MP4
          </div>
        </div>
        <b-button v-show="isDiscussIt"
                  size="sm"
                  variant="info"
                  style="vertical-align: top"
                  @click="$emit('initDiscussItText')"
        >
          Add Text
        </b-button>
        <b-modal id="modal-upload-question-media"
                 title="Upload Question Media and Edit Transcript"
                 size="lg"
                 no-close-on-backdrop
        >
          <div>
            <p>
              The following video explains how to have ADAPT automatically add a transcript to your audio/video media.
              This transcript can then be
              edited as needed.
            </p>
          </div>
          <b-embed
            type="iframe"
            aspect="16by9"
            src="https://www.youtube.com/embed/tvtGLadJ4u0?rel=0"
            allowfullscreen
          />
          <template #modal-footer>
            <b-button
              size="sm"
              variant="primary"
              class="float-right"
              @click="$bvModal.hide('modal-upload-question-media')"
            >
              OK
            </b-button>
          </template>
        </b-modal>
      </div>
    </b-row>
    <b-row class="upload mt-3 ml-1">
      <div v-if="files.length && (preSignedURL !== '')">
        <div v-for="file in files" :key="file.id">
          File to upload:
          <span :class="file.success ? 'text-success font-weight-bold' : ''">{{
              file.name
            }}</span> -
          <span>{{ formatFileSize(file.size) }} </span>
          <b-button
            v-if="(preSignedURL !== '') && (!$refs.questionMediaUpload || !$refs.questionMediaUpload.active)"
            variant="info"
            size="sm"
            style="vertical-align: top"
            :disabled="disableStartUpload"
            @click.prevent="initStartUpload('questionMediaUpload')"
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
                    @click.prevent="cancelUpload"
          >
            Cancel
          </b-button>
          <div v-if="file.error" class="text-danger">
            Error: {{ file.error }}
          </div>
        </div>
      </div>
    </b-row>
    <b-progress v-if="preSignedURL && !isVttUpload" max="100" class="mt-2 mb-3">
      <b-progress-bar :value="progress" :label="`${Number(progress).toFixed(0)}%`" show-progress animated/>
    </b-progress>
    <b-row v-if="uploadFileErrorMessage" class="mb-3">
      <ErrorMessage :message="uploadFileErrorMessage"/>
    </b-row>
    <div v-if="mediaUploads.length>0">
      <table class="table table-striped" aria-label="Media">
        <thead>
        <tr>
          <th v-if="isDiscussIt" scope="col">
            Order
          </th>
          <th scope="col">
            Filename
          </th>
          <th scope="col">
            Actions
          </th>
        </tr>
        </thead>
        <tbody is="draggable" :key="mediaUploads.length" v-model="orderedMediaUploads" tag="tbody"
               @end="updateQuestionMediaUploadsOrder"
        >
        <tr
          v-for="(mediaUpload, mediaUploadIndex) in mediaUploads"
          :key="`media-upload-${mediaUploadIndex}`"
        >
          <th v-if="isDiscussIt" scope="row">
            <b-icon icon="list"/>
            {{ mediaUpload.order }}
          </th>
          <td style="width:80%">
            {{ mediaUpload.original_filename }}
          </td>

          <td>
            <span v-show="false" :id="`copy-question-media-url-${mediaUpload.s3_key}`">{{ mediaUpload.url }}</span>
            <a v-show="!isDiscussIt"
               :id="getTooltipTarget('copyURL',mediaUpload.s3_key)"
               href=""
               aria-label="Copy URL"
               style="cursor: pointer;color:#212529 !important"
               class="mr-1"
               @click.prevent="doCopy(`copy-question-media-url-${mediaUpload.s3_key}`, 'Successfully copied!  You may add this URL to a link in your question.')"
            >
              <font-awesome-icon :icon="copyIcon"/>
            </a>
            <b-tooltip :target="getTooltipTarget('copyURL',mediaUpload.s3_key)"
                       triggers="hover"
                       delay="500"
            >
              Copy the URL, then create a link inside the question editor and ADAPT will convert the format the media
              for
              listening/viewing.
            </b-tooltip>
            <span v-show="needsTranscript(mediaUpload)">
                <b-icon :id="getTooltipTarget('editCaptions',mediaUpload.s3_key)"
                        icon="pencil"
                        :aria-label="isVideo(mediaUpload) ? 'Edit video size' : 'Edit question transcription'"
                        style="cursor: pointer;"
                        class="mr-1"
                        @click="showQuestionMedia(mediaUpload)"
                />
                <b-tooltip :target="getTooltipTarget('editCaptions',mediaUpload.s3_key)"
                           triggers="hover"
                           delay="500"
                >
                  {{ isVideo(mediaUpload) ? 'Edit Video Size' : 'View Video' }}
                </b-tooltip>
                <span v-show="mediaUpload.id && mediaUpload.transcript">
                  <a v-show="false"
                     :id="`download-transcript-${mediaUpload.id}`"
                     :href="`/api/question-media/${mediaUpload.id}/download-transcript`"
                  >Download Transcript</a>
                  <b-icon :id="getTooltipTarget('downloadTranscript',mediaUpload.s3_key)"
                          icon="download"
                          :aria-label="`Download Transcript`"
                          style="cursor: pointer;"
                          class="mr-1"
                          @click="downloadTranscript(mediaUpload)"
                  />
                  <b-tooltip :target="getTooltipTarget('downloadTranscript',mediaUpload.s3_key)"
                             triggers="hover"
                             delay="500"
                  >
                    Download transcript for {{ mediaUpload.original_filename }}
                  </b-tooltip>
                  <b-icon :id="getTooltipTarget('uploadTranscript',mediaUpload.s3_key)"
                          icon="upload"
                          :aria-label="`Upload Transcript`"
                          style="cursor: pointer;"
                          class="mr-1"
                          @click="uploadTranscript(mediaUpload)"
                  />
                  <b-tooltip :target="getTooltipTarget('uploadTranscript',mediaUpload.s3_key)"
                             triggers="hover"
                             delay="500"
                  >
                    Upload transcript (.vtt) file for {{ mediaUpload.original_filename }}
                  </b-tooltip>
                </span>
              </span>
            <b-icon :id="getTooltipTarget('deleteQuestionMedia',mediaUpload.s3_key)"
                    icon="trash"
                    :aria-label="`Delete question media`"
                    style="cursor: pointer;"
                    class="mr-1"
                    @click="confirmDeleteQuestionMedia(mediaUpload)"
            />
            <b-tooltip :target="getTooltipTarget('deleteQuestionMedia',mediaUpload.s3_key)"
                       triggers="hover"
                       delay="500"
            >
              Delete {{ mediaUpload.original_filename }}
            </b-tooltip>
          </td>
        </tr>
        </tbody>
      </table>
      <b-form-group v-if="!isDiscussIt"
                    label-cols-sm="3"
                    label-cols-lg="2"
                    label-for="show-captions"
      >
        <template #label>
          Show Captions
          <QuestionCircleTooltip id="show-captions-tooltip"/>
          <b-tooltip target="show-captions-tooltip"
                     delay="250"
                     triggers="hover focus"
          >ADAPT will use AI to automatically create captions. You can optionally hide these. Note that
            these will be shown to students if they require an accessible alternative.
          </b-tooltip>
        </template>
        <b-form-radio-group
          id="show-captions"
          v-model="showCaptions"
          class="mt-2"
          inline
        >
          <b-form-radio value="yes">Yes</b-form-radio>
          <b-form-radio value="no">No</b-form-radio>
        </b-form-radio-group>
      </b-form-group>
    </div>
  </b-container>
</template>
<script>
import axios from 'axios'
import { formatFileSize } from '~/helpers/UploadFiles'
import ErrorMessage from './ErrorMessage.vue'
import { fixInvalid } from '~/helpers/accessibility/FixInvalid'
import AllFormErrors from './AllFormErrors.vue'
import { v4 as uuidv4 } from 'uuid'
import Vue from 'vue'
import { doCopy } from '~/helpers/Copy'
import { faCopy } from '@fortawesome/free-regular-svg-icons'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { getTooltipTarget } from '~/helpers/Tooptips'
import QuestionCircleTooltipModal from './QuestionCircleTooltipModal.vue'
import draggable from 'vuedraggable'
import Transcript from './Transcript.vue'

const VueUploadComponent = require('vue-upload-component')
Vue.component('file-upload', VueUploadComponent)
export default {
  name: 'QuestionMediaUpload',
  components: {
    Transcript,
    QuestionCircleTooltipModal,
    ErrorMessage,
    AllFormErrors,
    FontAwesomeIcon,
    draggable
  },
  props: {
    isDiscussIt: {
      type: Boolean,
      default: false
    },
    qtiJson: {
      type: String,
      default: ''
    },
    questionMediaUploadId: {
      type: Number,
      default: 0
    },
    mediaUploads: {
      type: Array,
      default: () => {
      }
    }
  },
  watch: {
    showCaptions (newValue) {
      this.$emit('updateQtiJson', 'showCaptions', newValue)
    }
  },
  data: () => ({
    showCaptions: 'yes',
    isVttUpload: false,
    files2: [],
    copyIcon: faCopy,
    startTime: 0,
    questionMediaKey: 0,
    allFormErrors: [],
    questionMediaUpload: {},
    progress: 0,
    uploadFileErrorMessage: '',
    disableStartUpload: false,
    files: [],
    preSignedURL: '',
    modalId: '',
    activeMedia: {},
    activeTranscript: [],
    orderedMediaUploads: [],
    sizeWidth: null,
    sizeHeight: null,
    sizeMaintainAspectRatio: true,
    sizeRatio: null
  }),
  mounted () {
    this.modalId = uuidv4()
    this.orderedMediaUploads = this.isDiscussIt
      ? this.mediaUploads.sort((a, b) => a.order - b.order)
      : this.mediaUploads
    this.backfillNativeDimensions()

    if (this.questionMediaUploadId) {
      this.autoOpenTranscript()
    }
    console.error(this.qtiJson)
    try {
      this.showCaptions = JSON.parse(this.qtiJson).showCaptions === 'no' ? 'no' : 'yes'
    } catch {
      this.showCaptions = 'yes'
    }
  },
  methods: {
    getTooltipTarget,
    formatFileSize,
    fixInvalid,
    doCopy,
    updateTranscriptInMedia (caption, transcriptTiming, activeTranscriptTimingText) {
      this.activeMedia.transcript.find(caption => caption.start === transcriptTiming).text = activeTranscriptTimingText
      this.startTime = this.timeToSeconds(this.activeMedia.transcript[caption].start)
      this.questionMediaKey++
    },
    hideMediaModal () {
      this.$bvModal.hide('modal-question-media')
    },
    onSizeWidthInput () {
      if (this.sizeMaintainAspectRatio && this.sizeRatio && this.sizeWidth) {
        this.sizeHeight = Math.round(this.sizeWidth / this.sizeRatio)
      } else if (this.sizeWidth && this.sizeHeight) {
        this.sizeRatio = this.sizeWidth / this.sizeHeight
      }
    },
    onSizeHeightInput () {
      if (this.sizeMaintainAspectRatio && this.sizeRatio && this.sizeHeight) {
        this.sizeWidth = Math.round(this.sizeHeight * this.sizeRatio)
      } else if (this.sizeWidth && this.sizeHeight) {
        this.sizeRatio = this.sizeWidth / this.sizeHeight
      }
    },
    resetMediaSize () {
      this.sizeWidth = null
      this.sizeHeight = null
      this.saveMediaSize()
    },
    saveMediaSize () {
      const width = this.sizeWidth || null
      const height = this.sizeHeight || null
      this.activeMedia.width = width
      this.activeMedia.height = height
      this.$emit('updateQuestionMediaUploadDimensions', { s3_key: this.activeMedia.s3_key, width, height })
      this.$noty.success(width
        ? `${this.activeMedia.original_filename} will display at ${width}px wide once you save the question.`
        : `${this.activeMedia.original_filename} will fill the question area once you save the question.`)
    },
    isVideo (mediaUpload) {
      return /\.mp4$/i.test(mediaUpload.original_filename || '')
    },
    // Reads intrinsic width/height off a playable video URL (blob: or a signed
    // S3 URL) without ever attaching it to the DOM or playing it.
    probeVideoDimensions (src) {
      return new Promise((resolve) => {
        const probe = document.createElement('video')
        probe.preload = 'metadata'
        probe.onloadedmetadata = () => resolve({ width: probe.videoWidth, height: probe.videoHeight })
        probe.onerror = () => resolve(null)
        probe.src = src
      })
    },
    async detectVideoDimensions (newFile) {
      const dims = await this.probeVideoDimensions(newFile.blob)
      if (dims) {
        newFile.native_width = dims.width
        newFile.native_height = dims.height
      }
    },
    async fetchTemporaryUrl (s3Key) {
      try {
        const { data } = await axios.patch('/api/question-media/temporary-urls', {
          question_media_uploads: [{ s3_key: s3Key }]
        })
        return data && data.type === 'success' ? (data.question_media_uploads[0] || {}).temporary_url : null
      } catch (error) {
        console.error(error)
        return null
      }
    },
    // For media saved before native dimensions were being captured: fetch a
    // signed URL and probe it once, then persist the result via an emit so
    // this never has to happen again for that file.
    async backfillNativeDimensions () {
      const missing = this.mediaUploads.filter(item => this.isVideo(item) && (!item.native_width || !item.native_height))
      for (const item of missing) {
        const temporaryUrl = await this.fetchTemporaryUrl(item.s3_key)
        if (!temporaryUrl) continue
        const dims = await this.probeVideoDimensions(temporaryUrl)
        if (!dims) continue
        item.native_width = dims.width
        item.native_height = dims.height
        this.$emit('updateQuestionMediaUploadNativeDimensions', {
          s3_key: item.s3_key,
          native_width: dims.width,
          native_height: dims.height
        })
      }
    },
    updateQuestionMediaUploadsOrder () {
      for (let i = 0; i < this.orderedMediaUploads.length; i++) {
        this.orderedMediaUploads[i].order = i + 1
      }
      this.$emit('updateQuestionMediaUploadsOrder', this.orderedMediaUploads)
    },
    async downloadTranscript (activeMedia) {
      document.getElementById(`download-transcript-${activeMedia.id}`).click()
    },
    uploadTranscript (activeMedia) {
      this.activeMedia = activeMedia
      this.$bvModal.show('modal-upload-vtt')
    },
    confirmDeleteQuestionMedia (activeMedia) {
      this.activeMedia = activeMedia
      if (this.qtiJson.includes(activeMedia.s3_key) && !this.isDiscussIt) {
        this.$noty.error('Please remove the URL from the question before deleting this media.')
      } else {
        this.$bvModal.show('modal-confirm-delete-question-media')
      }
    },
    async deleteQuestionMedia () {
      try {
        if (this.activeMedia.id) {
          const { data } = await axios.delete(`/api/question-media/${this.activeMedia.id}`)
          this.$noty[data.type](data.message)
          if (data.type !== 'error') {
            this.$emit('deleteQuestionMediaUpload', this.activeMedia)
            this.$bvModal.hide('modal-confirm-delete-question-media')
          }
        } else {
          this.$noty.info(`${this.activeMedia.original_filename} has been deleted.`)
          this.$emit('deleteQuestionMediaUpload', this.activeMedia)
        }
      } catch (error) {
        this.$noty.error(error.message)
      }
    },
    cancelUpload () {
      this.files = []
      this.preSignedURL = ''
      this.progress = 0
      this.$noty.info('The upload has been canceled.')
    },
    autoOpenTranscript () {
      const activeMedia = this.mediaUploads.find(item => item.id === this.questionMediaUploadId)
      if (activeMedia) {
        this.activeMedia = activeMedia
        this.showQuestionMedia(activeMedia)
      }
    },
    timeToSeconds (time) {
      const [hours, minutes, seconds] = time.split(':').map(parseFloat)
      return (hours * 3600) + (minutes * 60) + seconds
    },
    needsTranscript (activeMedia) {
      return activeMedia.original_filename && !activeMedia.original_filename.endsWith('.pdf')
    },
    async showQuestionMedia (activeMedia) {
      this.activeMedia = activeMedia
      if (activeMedia.text) {
        this.$emit('editDiscussItText', activeMedia)
      } else {
        this.transcriptTiming = null
        this.activeTranscript = activeMedia.transcript
        if (this.needsTranscript(activeMedia)) {
          this.$bvModal.show('modal-question-media')
        }
      }
      if (this.isVideo(activeMedia)) {
        if (!activeMedia.native_width || !activeMedia.native_height) {
          const temporaryUrl = await this.fetchTemporaryUrl(activeMedia.s3_key)
          const dims = temporaryUrl ? await this.probeVideoDimensions(temporaryUrl) : null
          if (dims) {
            activeMedia.native_width = dims.width
            activeMedia.native_height = dims.height
            this.$emit('updateQuestionMediaUploadNativeDimensions', {
              s3_key: activeMedia.s3_key,
              native_width: dims.width,
              native_height: dims.height
            })
          }
        }
        this.sizeWidth = activeMedia.width || null
        this.sizeHeight = activeMedia.height || null
        this.sizeMaintainAspectRatio = true
        this.sizeRatio = (activeMedia.native_width && activeMedia.native_height)
          ? activeMedia.native_width / activeMedia.native_height
          : (this.sizeWidth && this.sizeHeight ? this.sizeWidth / this.sizeHeight : null)
      }
    },
    initStartUpload (fileUploadRef) {
      this.allFormErrors = []
      this.$refs[fileUploadRef].active = true
    },
    async inputUploadFileFilter (newFile, oldFile, prevent) {
      this.uploadFileErrorMessage = ''
      this.errorMessages = []
      if (newFile && !oldFile) {
        if (parseInt(newFile.size) > 30000000) {
          this.uploadFileErrorMessage = '30 MB max allowed.  Your file is too large.'
          this.$nextTick(() => fixInvalid())
          this.allFormErrors = [this.uploadFileErrorMessage]
          this.$bvModal.show(`modal-form-errors-file-upload-${this.modalId}`)
          return prevent()
        }
        let acceptedExtensionsRegex
        if (this.isVttUpload) {
          acceptedExtensionsRegex = /\.(vtt)$/i
        } else {
          acceptedExtensionsRegex = this.isDiscussIt ? /\.(pdf|mp3|mp4)$/i : /\.(mp3|mp4)$/i
        }
        const validExtension = acceptedExtensionsRegex.test(newFile.name)
        if (!validExtension) {
          this.uploadFileErrorMessage = `${newFile.name} does not have a valid extension.`
          this.$nextTick(() => fixInvalid())
          this.allFormErrors = [this.uploadFileErrorMessage]
          this.$bvModal.show(`modal-form-errors-file-upload-${this.modalId}`)
          return prevent()
        } else {
          try {
            this.preSignedURL = ''
            let uploadFileData = this.isVttUpload
              ? {
                upload_file_type: 'vtt',
                s3_key: this.activeMedia.s3_key,
                file_name: newFile.name
              }
              : {
                upload_file_type: 'question-media',
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
            newFile.question_media_filename = data.question_media_filename
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
      if (newFile.blob && /\.mp4$/i.test(newFile.name)) {
        await this.detectVideoDimensions(newFile)
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
    handleOK (newFile) {
      this.preSignedURL = ''
      this.disableStartUpload = false
      if (!this.isVttUpload) {
        this.questionMediaUpload = newFile
        this.questionMediaUpload.url = window.location.origin + `/question-media-player/${this.questionMediaUpload.question_media_filename}`
        this.$emit('updateQuestionMediaUploads', {
          original_filename: this.originalFilename,
          size: this.files[0].size,
          s3_key: this.questionMediaUpload.question_media_filename,
          url: this.questionMediaUpload.url,
          transcript: '',
          native_width: newFile.native_width || null,
          native_height: newFile.native_height || null,
          width: null,
          height: null
        })
      } else {
        this.validateVTTFile()
      }
    },
    async validateVTTFile () {
      try {
        const { data } = await axios.get(`/api/question-media/validate-vtt/${this.activeMedia.id}`)
        this.$noty[data.type](data.message)
        if (data.type === 'success') {
          this.$emit('updateQuestionTranscript', this.activeMedia, data.transcript)
          this.$bvModal.hide('modal-upload-vtt')
        }
      } catch (error) {
        this.$noty.error(error.message)
      }
    }
  }
}
</script>
