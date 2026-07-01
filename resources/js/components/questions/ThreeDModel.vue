<template>
  <div>
    <b-modal id="modal-3d-modal"
             :title="`ID ${activeIndex}`"
             no-close-on-esc
             no-close-on-backdrop
             size="xl"
    >
      <iframe
        :id="`threeDModel-feedback-${activeIndex}`"
        v-resize="{ log: false }"
        width="100%"
        height="600px"
        allowtransparency="true"
        :src="feedbackSrc"
        frameborder="0"
        @load="init3DModel(`threeDModel-feedback-${activeIndex}`)"
      />
      <template #modal-footer="{  ok }">
        <b-button size="sm"
                  variant="primary"
                  @click="$bvModal.hide(`threeDModel-feedback-${activeIndex}`)"
        >
          OK
        </b-button>
      </template>
    </b-modal>
    <b-card
      border-variant="primary"
      class="mb-3"
      header-html="<h5>Parameters</h5>"
    >
      <b-form-group
        label-cols-sm="3"
        label-cols-lg="2"
        label-for="modelID"
        label-size="sm"
      >
        <template #label>
          Model ID
          <QuestionCircleTooltip id="model-id-tooltip"/>
          <b-tooltip target="model-id-tooltip"
                     delay="250"
                     triggers="hover focus"
          >
            A link to a 3D model. Under the hood, this is a simple GET request, so any file location works.
            The renderer can work with .GLB, .OBJ, and .STL models.
          </b-tooltip>
        </template>
        <b-form-row>
          <b-form-input
            id="modelID"
            v-model="parametersForm.modelID"
            type="text"
            :class="{ 'is-invalid': parametersForm.errors.has('modelID') }"
            @keydown="parametersForm.errors.clear('modelID')"
          />
          <has-error :form="parametersForm" field="modelID"/>
        </b-form-row>
      </b-form-group>
      <b-form-group label-cols-sm="3" label-cols-lg="2" label-for="BGImage" label-size="sm">
        <template #label>
          BGImage
          <QuestionCircleTooltip id="BGImage-tooltip"/>
          <b-tooltip target="BGImage-tooltip" delay="250" triggers="hover focus">
            Expects a link to an image.
          </b-tooltip>
        </template>
        <b-form-row>
          <b-form-input
            id="BGImage"
            v-model="parametersForm.BGImage"
            type="text"
            :class="{ 'is-invalid': parametersForm.errors.has('BGImage') }"
            @keydown="parametersForm.errors.clear('BGImage')"
          />
          <has-error :form="parametersForm" field="BGImage"/>
        </b-form-row>
      </b-form-group>
      <b-form-group label-cols-sm="3" label-cols-lg="2" label-for="annotations" label-size="sm">
        <template #label>
          Annotations
          <QuestionCircleTooltip id="annotations-tooltip"/>
          <b-tooltip target="annotations-tooltip" delay="250" triggers="hover focus">
            A reference to a JSON file containing annotations.
            Each annotation needs an index (to attach to a piece), text (display text), position
            (an x, y, z vector noting position)
            and camPos (an x, y, z vector to send the camera to when an annotation is clicked)
          </b-tooltip>
        </template>
        <b-form-row>
          <b-form-input
            id="BGImage"
            v-model="parametersForm.annotations"
            type="text"
            :class="{ 'is-invalid': parametersForm.errors.has('annotations') }"
            @keydown="parametersForm.errors.clear('annotations')"
          />
          <has-error :form="parametersForm" field="annotations"/>
        </b-form-row>
      </b-form-group>
      <b-form-group label="Show Panel" label-cols-sm="3" label-cols-lg="2" label-for="panel" label-size="sm">
        <b-form-radio-group
          id="panel"
          v-model="parametersForm.panel"
          name="panel"
          class="pt-2"
        >
          <b-form-row>
            <b-form-radio value="yes">
              yes
            </b-form-radio>
            <b-form-radio value="no">
              no
            </b-form-radio>
          </b-form-row>
        </b-form-radio-group>
      </b-form-group>
      <b-form-group label="Autospin" label-cols-sm="3" label-cols-lg="2" label-for="autospin" label-size="sm">
        <b-form-radio-group
          id="autospin"
          v-model="parametersForm.autospin"
          name="autospin"
          class="pt-2"
        >
          <b-form-row>
            <b-form-radio value="yes">
              yes
            </b-form-radio>
            <b-form-radio value="no">
              no
            </b-form-radio>
          </b-form-row>
        </b-form-radio-group>
      </b-form-group>
      <b-row>
        <b-col md="6">
          <b-form-group label-cols-sm="4" label-cols-lg="3" label-for="BGColor" label-size="sm">
            <template #label>
              BGColor
              <QuestionCircleTooltip id="BGColor-tooltip"/>
              <b-tooltip target="BGColor-tooltip" delay="250" triggers="hover focus">
                Expects a hex value without the leading "#".
              </b-tooltip>
            </template>
            <b-form-row>
              <b-form-input
                id="BGColor"
                v-model="parametersForm.BGColor"
                type="text"
                :class="{ 'is-invalid': parametersForm.errors.has('BGColor') }"
                @keydown="parametersForm.errors.clear('BGColor')"
              />
              <has-error :form="parametersForm" field="BGColor"/>
            </b-form-row>
          </b-form-group>
        </b-col>

        <b-col md="6">
          <b-form-group label-cols-sm="4" label-cols-lg="3" label-for="modelOffset" label-size="sm">
            <template #label>
              modelOffset
              <QuestionCircleTooltip id="modelOffset-tooltip"/>
              <b-tooltip target="modelOffset-tooltip" delay="250" triggers="hover focus">
                Comma-separated vector for model offset (e.g. 0,0,0).
              </b-tooltip>
            </template>
            <b-form-row>
              <b-form-input
                id="modelOffset"
                v-model="parametersForm.modelOffset"
                type="text"
                :class="{ 'is-invalid': parametersForm.errors.has('modelOffset') }"
                @keydown="parametersForm.errors.clear('modelOffset')"
              />
              <has-error :form="parametersForm" field="modelOffset"/>
            </b-form-row>
          </b-form-group>
        </b-col>

        <b-col md="6">
          <b-form-group label-cols-sm="4" label-cols-lg="3" label-for="cameraOffset" label-size="sm">
            <template #label>
              cameraOffset
              <QuestionCircleTooltip id="cameraOffset-tooltip"/>
              <b-tooltip target="cameraOffset-tooltip" delay="250" triggers="hover focus">
                Z offset of the camera on load (default 2.25).
              </b-tooltip>
            </template>
            <b-form-row>
              <b-form-input
                id="cameraOffset"
                v-model="parametersForm.cameraOffset"
                type="text"
                :class="{ 'is-invalid': parametersForm.errors.has('cameraOffset') }"
                @keydown="parametersForm.errors.clear('cameraOffset')"
              />
              <has-error :form="parametersForm" field="cameraOffset"/>
            </b-form-row>
          </b-form-group>
        </b-col>

        <b-col md="6">
          <b-form-group label-cols-sm="4" label-cols-lg="3" label-for="selectionColor" label-size="sm">
            <template #label>
              selectionColor
              <QuestionCircleTooltip id="selectionColor-tooltip"/>
              <b-tooltip target="selectionColor-tooltip" delay="250" triggers="hover focus">
                Hex color(s) for selected pieces, omit leading "#".
              </b-tooltip>
            </template>
            <b-form-row>
              <b-form-input
                id="selectionColor"
                v-model="parametersForm.selectionColor"
                type="text"
                :class="{ 'is-invalid': parametersForm.errors.has('selectionColor') }"
                @keydown="parametersForm.errors.clear('selectionColor')"
              />
              <has-error :form="parametersForm" field="selectionColor"/>
            </b-form-row>
          </b-form-group>
        </b-col>

        <b-col md="6">
          <b-form-group label-cols-sm="4" label-cols-lg="3" label-for="STLmatCol" label-size="sm">
            <template #label>
              STLmatCol
              <QuestionCircleTooltip id="STLmatCol-tooltip"/>
              <b-tooltip target="STLmatCol-tooltip" delay="250" triggers="hover focus">
                Color of STL model (hex without #).
              </b-tooltip>
            </template>
            <b-form-row>
              <b-form-input
                id="STLmatCol"
                v-model="parametersForm.STLmatCol"
                type="text"
                :class="{ 'is-invalid': parametersForm.errors.has('STLmatCol') }"
                @keydown="parametersForm.errors.clear('STLmatCol')"
              />
              <has-error :form="parametersForm" field="STLmatCol"/>
            </b-form-row>
          </b-form-group>
        </b-col>

        <b-col md="6">
          <b-form-group label-cols-sm="4" label-cols-lg="3" label-for="hideDistance" label-size="sm">
            <template #label>
              hideDistance
              <QuestionCircleTooltip id="hideDistance-tooltip"/>
              <b-tooltip target="hideDistance-tooltip" delay="250" triggers="hover focus">
                Distance from camera at which to hide annotations (default 5).
              </b-tooltip>
            </template>
            <b-form-row>
              <b-form-input
                id="hideDistance"
                v-model="parametersForm.hideDistance"
                type="text"
                :class="{ 'is-invalid': parametersForm.errors.has('hideDistance') }"
                @keydown="parametersForm.errors.clear('hideDistance')"
              />
              <has-error :form="parametersForm" field="hideDistance"/>
            </b-form-row>
          </b-form-group>
        </b-col>
      </b-row>
    </b-card>
    <div style="height: 600px; overflow: hidden;">
      <iframe
        id="threeDModel-create-question"
        v-resize="{ log: false }"
        width="100%"
        height="600px"
        allowtransparency="true"
        :src="src"
        frameborder="0"
        style="display: block;"
        @load="init3DModel('threeDModel-create-question')"
      />
    </div>
    <ErrorMessage v-if="threeDModelSolutionStructureErrors" :message="threeDModelSolutionStructureErrors"/>
    <b-card v-if="feedbacks.length"
            header-html="<h2 class=&quot;h5&quot;>Feedback</h2>"
    >
      <b-card-text>
        <b-form-group
          label-for="general-incorrect-feedback"
        >
          <template #label>
            General Incorrect Feedback (optional)
            <QuestionCircleTooltip id="general-incorrect-feedback-tooltip"/>
            <b-tooltip target="general-incorrect-feedback-tooltip"
                       delay="250"
                       triggers="hover focus"
            >If a student incorrectly answers the question, this feedback will be provided. This can be overridden below
              with more specific feedback.
            </b-tooltip>
          </template>
          <ckeditor
            id="general-incorrect-feedback"
            v-model="generalIncorrectFeedback"
            tabindex="0"
            :config="threeDModelRichEditorConfig"
            @namespaceloaded="onCKEditorNamespaceLoaded"
            @ready="handleFixCKEditor()"
          />
        </b-form-group>
        <b-row v-for="(feedback, index) in feedbacks" :key="`feedback-${feedback.originalIndex}`">
          <b-col cols="auto" class="d-flex justify-content-center align-items-center flex-shrink-0"
                 style="width: 100px; max-width: 100px;"
          >
            <b-button @click="show3DModelModal(feedback.originalIndex)"
                      :variant="(lastClickedIndex !== null || (qtiJson.solutionStructure && qtiJson.solutionStructure.selectedIndex >= 0)) ? (index === 0 ? 'success' : 'danger') : ''"
            >
              ID {{ feedback.originalIndex }}
            </b-button>
          </b-col>
          <b-col>
            <b-form-group
              :label-for="`feedback_${feedback.originalIndex}`"
              label="Feedback (Optional)"
            >
              <ckeditor
                :id="`feedback_${feedback.originalIndex}`"
                :key="`ckeditor-${feedback.originalIndex}-${reorderKey}`"
                v-model="feedbacks[index].value"
                tabindex="0"
                :config="threeDModelRichEditorConfig"
                @namespaceloaded="onCKEditorNamespaceLoaded"
                @ready="handleFixCKEditor()"
              />
            </b-form-group>
          </b-col>
        </b-row>
      </b-card-text>
    </b-card>
  </div>
</template>

<script>
import Form from 'vform/src'
import { create3DModelSrc } from '~/helpers/Questions'
import ErrorMessage from '../ErrorMessage.vue'
import { fixCKEditor } from '../../helpers/accessibility/fixCKEditor'
import CKEditor from 'ckeditor4-vue'

export default {
  name: 'ThreeDModel',
  components: {
    ErrorMessage,
    ckeditor: CKEditor.component
  },
  props: {
    threeDModelRichEditorConfig: {
      type: Object,
      default: () => {
      }
    },
    qtiJson: {
      type: Object,
      default: () => {
      }
    },
    threeDModelParameterErrors: {
      type: Array,
      default: () => {
      }
    },
    threeDModelSolutionStructureErrors: {
      type: String,
      default: ''
    }
  },
  data: () => ({
    activeIndex: 0,
    reorderKey: 0,
    correctIndex: null,
    lastClickedIndex: null,
    feedbacks: [],
    generalIncorrectFeedback: '',
    numPieces: 0,
    feedbackSrc: '',
    src: '',
    parametersForm: new Form(),
    isReady: []
  }),
  watch: {
    'feedbacks': {
      handler (newValue) {
        const sorted = [...newValue]
          .sort((a, b) => a.originalIndex - b.originalIndex)
          .map(f => f.value)
        this.$emit('updateQtiJson', 'feedbacks', sorted)
      },
      deep: true
    },
    'generalIncorrectFeedback': {
      handler (newValue) {
        this.$emit('updateQtiJson', 'generalIncorrectFeedback', newValue)
      }
    },
    'parametersForm': {
      handler (newValue, oldValue) {
        const values = { ...newValue }
        this.updateSrc()
        this.$emit('updateQtiJson', 'parameters', values)
      },
      deep: true
    },
    threeDModelParameterErrors (value) {
      if (Array.isArray(value)) {
        const errors = JSON.parse(value[0])
        for (const [key, value] of Object.entries(errors)) {
          if (this.parametersForm.hasOwnProperty(key)) {
            this.parametersForm.errors.set(key, value)
          }
        }
      }
    }
  },
  created () {
    window.addEventListener('message', this.receiveMessage, false)
  },
  destroyed () {
    window.removeEventListener('message', this.receiveMessage)
  },
  mounted () {
    this.parametersForm = new Form(this.qtiJson.parameters)
    if (this.qtiJson.solutionStructure !== undefined) {
      this.correctIndex = this.qtiJson.solutionStructure.selectedIndex
    }
    // CHANGED: load existing general incorrect feedback when editing a saved question
    if (this.qtiJson.generalIncorrectFeedback) {
      this.generalIncorrectFeedback = this.qtiJson.generalIncorrectFeedback
    }
  },
  methods: {
    create3DModelSrc,
    isCorrectAnswer (index) {
      return this.correctIndex === index
    },
    show3DModelModal (index) {
      this.activeIndex = index
      const isFirst = this.feedbacks.length > 0 && this.feedbacks[0].originalIndex === index
      const selectionColor = isFirst ? '008600' : 'dc3545'
      this.feedbackSrc = `https://devapp02.libretexts.org/?modelID=${this.parametersForm.modelID}&mode=selection&panel=hide&autospin=no&hideControlsButton=true&allowSelection=false&selectionColor=${selectionColor}` + '&v=' + Date.now()
      this.$bvModal.show('modal-3d-modal')
    },
    receiveMessage (event) {
      console.error(event.data)
      if (event.data.info === 'count') {
        console.error('getting the count')
        if (this.feedbacks.length !== event.data.num && event.data.num >= 0) {
          const feedbacks = this.qtiJson.feedbacks
          if (Array.isArray(feedbacks)) {
            this.feedbacks = Array.from({ length: event.data.num }, (_, i) => ({
              originalIndex: i,
              value: feedbacks[i] || ''
            }))
          } else {
            this.feedbacks = Array.from({ length: event.data.num }, (_, i) => ({
              originalIndex: i,
              value: ''
            }))
          }
          // On load, move the correct answer to the top
          const correctIdx = this.qtiJson.solutionStructure && this.qtiJson.solutionStructure.selectedIndex >= 0
            ? this.qtiJson.solutionStructure.selectedIndex
            : null
          if (correctIdx !== null) {
            const correct = this.feedbacks.find(f => f.originalIndex === correctIdx)
            const rest = this.feedbacks
              .filter(f => f.originalIndex !== correctIdx)
              .sort((a, b) => a.originalIndex - b.originalIndex)
            this.feedbacks = [correct, ...rest]
            this.reorderKey++
          }
        }
        console.error(event.data.num)
      }
      if (event.data.info === 'selectedPiece') {
        const creationIframe = document.getElementById('threeDModel-create-question')
        if (creationIframe && event.source === creationIframe.contentWindow) {
          const selected = event.data.selected
          this.lastClickedIndex = selected
          const clicked = this.feedbacks.find(f => f.originalIndex === selected)
          const rest = this.feedbacks
            .filter(f => f.originalIndex !== selected)
            .sort((a, b) => a.originalIndex - b.originalIndex)
          this.feedbacks = [clicked, ...rest]
          this.reorderKey++
          this.$emit('updateQtiJson', 'solutionStructure', { selectedIndex: selected })
        }
      }
      if (event.data.info === 'isReady') {
        const id = event.data.id
        const threeDModelView = document.getElementById(id)
        if (event.data.status === true) {
          this.isReady[id] = true
          if (id === 'threeDModel-create-question') {
            if (this.qtiJson && this.qtiJson.solutionStructure) {
              const response = {
                type: 'load3DModel',
                modelInfo: {
                  selectedIndex: this.qtiJson.solutionStructure.selectedIndex
                }
              }
              threeDModelView.contentWindow.postMessage(response, '*')
              this.$emit('updateQtiJson', 'solutionStructure', { selectedIndex: this.qtiJson.solutionStructure.selectedIndex })
            }
            threeDModelView.contentWindow.postMessage('pieceCount', '*')
          } else {
            const index = id.replace('threeDModel-feedback-', '')
            const response = {
              type: 'load3DModel',
              modelInfo: {
                selectedIndex: +index
              }
            }
            threeDModelView.contentWindow.postMessage(response, '*')
          }
        } else {
          console.log('model not quite ready yet')
        }
      }
    },
    init3DModel (id) {
      this.isReady[id] = false
      const interval = setInterval(() => {
        const threeDModelView = document.getElementById(id)
        if (this.isReady[id] === false && threeDModelView) {
          threeDModelView.contentWindow.postMessage({ message: 'ready', id: id }, '*')
        } else {
          clearInterval(interval)
        }
      }, 50)
    },
    updateSrc () {
      const base = this.create3DModelSrc(this.parametersForm)
      this.src = base.replace(/selectionColor=[^&]*/g, 'selectionColor=008600')
        + (base.includes('selectionColor') ? '' : '&selectionColor=008600')
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
