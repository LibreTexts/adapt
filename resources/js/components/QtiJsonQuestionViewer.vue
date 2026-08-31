<template>
  <div class="pb-3 pr-3 pl-3 pt-2">
    <b-modal
      id="modal-confirm-structure"
      title="Confirm Structure"
      @hidden="confirmedStructure = true"
    >
      Before submitting, verify that the molecule in the sketcher accurately reflected the
      molecule in the upload image or photo and if not use the sketcher to edit the molecule as needed.
      <template #modal-footer>
        <b-button
          size="sm"
          variant="primary"
          class="float-right"
          @click="$bvModal.hide('modal-confirm-structure')"
        >
          OK
        </b-button>
      </template>
    </b-modal>
    <div v-if="question.jsonType === 'question_json'">
      <b-modal
        id="modal-submission-error"
        title="Submission Not Accepted"
        size="lg"
        hide-footer
      >
        <b-alert variant="danger" :show="true">
          <span style="font-size: large">{{ submissionErrorMessage }}</span>
        </b-alert>
      </b-modal>
    </div>
    <div v-show="showRandomizedMessage()">
      <b-alert
        show
        variant="info"
      >
        Students will receive a randomized ordering of possible responses.
      </b-alert>
    </div>
    <b-alert v-if="user.role === 2
               && ['multiple_choice','select_choice'].includes(questionType)
               && JSON.parse(qtiJson).randomizeOrder === 'no'"
             show
             variant="info"
    >
      Students will <strong>not</strong> receive a randomized ordering of possible responses.
    </b-alert>
    <div :id="showQtiAnswer ? 'answer' : 'question'">
      <SelectChoiceDropDownRationaleViewer
        v-if="['select_choice','drop_down_rationale','drop_down_rationale_triad'].includes(questionType)"
        ref="dropDownTableViewer"
        :key="`select-choice-drop-down-rational-${qtiJsonCacheKey}`"
        :qti-json="JSON.parse(qtiJson)"
        :show-response-feedback="showResponseFeedback"
      />

      <FillInTheBlankViewer v-if="questionType === 'fill_in_the_blank'"
                            ref="fillInTheBlankViewer"
                            :key="`fill-in-the-blank-${qtiJsonCacheKey}`"
                            :qti-json="JSON.parse(qtiJson)"
                            :show-response-feedback="showResponseFeedback"
      />
      <AccountingJournalEntryViewer v-if="questionType === 'accounting_journal_entry'"
                                    ref="accountingJournalEntryViewer"
                                    :key="`accounting-journal-${qtiJsonCacheKey}`"
                                    :qti-json="JSON.parse(qtiJson)"
      />
      <FlashcardViewer
        v-if="questionType === 'flashcard'"
        ref="flashcardViewer"
        :key="`flashcard-${qtiJsonCacheKey}-${parsedQtiJson}`"
        :cards="allFlashcards.length ? allFlashcards : (questionType === 'flashcard' ? [parsedQtiJson.card] : [])"
        :assignment-id="assignmentId"
        :question-id="questionId"
        :initial-question-id="initialQuestionId"
        :previewing-question="previewingQuestion"
        :flashcard-settings="flashcardSettings"
        :assessment-type="assessmentType"
        @selfReported="onSelfReported"
        @controlsStateChanged="$emit('flashcardControlsChanged', $event)"
        @cardChanged="allFlashcards.length > 1 ? $emit('cardChanged', $event) : null"
      />
      <div
        v-if="['accounting_report',
               'accounting_multi_part_computation',
               'forge',
               'forge_iteration',
               'three_d_model_multiple_choice',
               'three_d_model_multiple_answer',
               'submit_molecule',
               'marker',
               'matching',
               'true_false',
               'multiple_choice',
               'multiple_answers',
               'numerical',
               'multi_numerical',
               'multiple_response_select_all_that_apply',
               'multiple_response_select_n',
               'matrix_multiple_response',
               'matrix_multiple_choice',
               'multiple_response_grouping',
               'drop_down_table',
               'highlight_table',
               'bow_tie'].includes(questionType)"
      >
        <div style="font-family: Sans-Serif,serif;" :style="presentationMode ? 'font-size:20px' : 'font-size:16px'">
          <span
            v-html="['accounting_report','accounting_multi_part_computation'].includes(questionType) ? escapeDollar(prompt) : prompt"
          />
        </div>
        <AccountingMultiPartComputationViewer
          v-if="questionType === 'accounting_multi_part_computation'"
          ref="accountingMultiPartComputationViewer"
          :key="`accounting-multi-part-computation-${qtiJsonCacheKey}`"
          :qti-json="JSON.parse(qtiJson)"
          :show-answer="showQtiAnswer"
        />
        <b-form-group>
          <div>
            <div v-if="['submit_molecule', 'marker'].includes(questionType)
              && user.role === 2
              && +JSON.parse(qtiJson).matchStereo === 1"
            >
              <b-alert show variant="info">
                Only identical stereoisomers will be approved for this question.
              </b-alert>
            </div>
            <iframe
              v-if="questionType === 'submit_molecule' && previewingQuestion"
              id="emptySketcher"
              v-resize="{ log: false }"
              width="100%"
              src="/api/sketcher/empty"
              frameborder="0"
            />
          </div>
          <SketcherViewer
            v-if="['submit_molecule', 'marker'].includes(questionType) && !previewingQuestion || 'marker' === questionType && previewingQuestion"
            ref="sketcherViewer"
            :key="`sketcher-${qtiJsonCacheKey}-${previewingQuestion}`"
            :qti-json="JSON.parse(qtiJson)"
            :student-response="studentResponse ? JSON.stringify(JSON.parse(studentResponse).structure) : solutionStructure"
            :read-only="previewOrSolution || !submitButtonActive"
            :configuration="questionType === 'marker' ? 'marker-only' : 'default'"
          />

          <ThreeDModelViewer
            v-if="['three_d_model_multiple_choice','three_d_model_multiple_answer'].includes(questionType)"
            :qti-json="JSON.parse(qtiJson)"
            :submit-button-active="submitButtonActive"
          />
          <AccountingReportViewer v-if="questionType === 'accounting_report'"
                                  ref="accountingReportViewer"
                                  :key="`accounting-report-${qtiJsonCacheKey}`"
                                  :qti-json="JSON.parse(qtiJson)"
                                  :show-answer="showQtiAnswer"
          />
          <DropDownTableViewer v-if="questionType === 'drop_down_table'"
                               ref="dropDownTableViewer"
                               :key="`drop-down-${qtiJsonCacheKey}`"
                               :qti-json="JSON.parse(qtiJson)"
                               :show-response-feedback="showResponseFeedback"
          />
          <MultipleResponseGroupingViewer v-if="questionType === 'multiple_response_grouping'"
                                          ref="multipleResponseGroupingViewer"
                                          :key="`multiple-response-grouping-${qtiJsonCacheKey}`"
                                          :qti-json="JSON.parse(qtiJson)"
                                          :show-response-feedback="showResponseFeedback"
          />
          <BowTieViewer v-if="questionType === 'bow_tie'"
                        ref="bowTieViewer"
                        :key="`bow-tie-${qtiJsonCacheKey}`"
                        :qti-json="JSON.parse(qtiJson)"
                        :show-response-feedback="showResponseFeedback"
          />
          <MatrixMultipleResponseViewer
            v-if="questionType === 'matrix_multiple_response'"
            ref="matrixMultipleResponseViewer"
            :key="`matrix-multiple-response-${qtiJsonCacheKey}`"
            :qti-json="JSON.parse(qtiJson)"
            :show-response-feedback="showResponseFeedback"
          />
          <MultipleResponseSelectAllThatApplyOrSelectNViewer
            v-if="['multiple_response_select_n','multiple_response_select_all_that_apply'].includes(questionType)"
            ref="multipleResponseSelectAllThatApplyOrSelectNViewer"
            :key="`multiple-response-select-all-that-apply-${qtiJsonCacheKey}`"
            :qti-json="JSON.parse(qtiJson)"
            :show-response-feedback="showResponseFeedback"
          />
          <SingleNumericalViewer v-if="questionType === 'numerical'"
                                 ref="numericalViewer"
                                 :key="`numerical-${qtiJsonCacheKey}`"
                                 :qti-json="JSON.parse(qtiJson)"
                                 :show-response-feedback="showResponseFeedback"
                                 :prompt="JSON.parse(qtiJson).prompt || ''"
          />
          <MultiNumericalViewer v-if="questionType === 'multi_numerical'"
                                ref="multiNumericalViewer"
                                :key="`multi-numerical-${qtiJsonCacheKey}`"
                                :qti-json="JSON.parse(qtiJson)"
                                :show-response-feedback="showResponseFeedback"
          />
          <MatchingViewer v-if="questionType === 'matching'"
                          ref="matchingViewer"
                          :key="`matching-${qtiJsonCacheKey}`"
                          :qti-json="JSON.parse(qtiJson)"
                          :show-submit="showSubmit"
          />
          <MultipleChoiceTrueFalseViewer v-if="['true_false','multiple_choice'].includes(questionType)"
                                         ref="multipleChoiceTrueFalseViewer"
                                         :key="`multiple-choice-true-false-${qtiJsonCacheKey}`"
                                         :qti-json="JSON.parse(qtiJson)"
                                         :presentation-mode="presentationMode"
          />
          <MultipleAnswersViewer v-if="questionType === 'multiple_answers'"
                                 ref="multipleAnswersViewer"
                                 :key="`multiple-answers-${qtiJsonCacheKey}`"
                                 :qti-json="JSON.parse(qtiJson)"
                                 :show-response-feedback="showResponseFeedback"
          />
          <MatrixMultipleChoiceViewer v-if="questionType === 'matrix_multiple_choice'"
                                      ref="matrixMultipleChoiceViewer"
                                      :key="`matrix-multiple-choice-${qtiJsonCacheKey}`"
                                      :qti-json="JSON.parse(qtiJson)"
                                      :show-response-feedback="showResponseFeedback"
          />
        </b-form-group>
      </div>
      <div v-if="questionType === 'discuss_it' && previewingQuestion">
        <DiscussItViewer v-if="questionType === 'discuss_it'"
                         ref="discussItViewer"
                         :key="`discussIt-${assignmentId}-${questionId}`"
                         :qti-json="JSON.parse(qtiJson)"
                         :question-id="questionId"
                         :assignment-id="assignmentId"
                         :can-start-discussion-or-add-comments="submitButtonActive"
                         :previewing-question="previewingQuestion"
                         @openContactGraderModal="openContactGraderModal"
        />
      </div>
      <DiscussItViewer v-if="questionType === 'discuss_it' && assignmentId"
                       ref="discussItViewer"
                       :key="`discussIt-${assignmentId}-${questionId}`"
                       :qti-json="JSON.parse(qtiJson)"
                       :question-id="questionId"
                       :assignment-id="assignmentId"
                       :can-start-discussion-or-add-comments="submitButtonActive"
                       :previewing-question="previewingQuestion"
                       @openContactGraderModal="openContactGraderModal"
      />
      <ForgeViewer v-if="(questionType === 'forge' || questionType === 'forge_iteration') && assignmentId"
                   :key="`forge-viewer-${questionId}`"
                   :user="user"
                   :assignment-id="assignmentId"
                   :question-id="questionId"
      />

      <DragAndDropClozeViewer
        v-if="questionType === 'drag_and_drop_cloze'"
        ref="dragAndDropClozeViewer"
        :qti-json="JSON.parse(qtiJson)"
        :show-response-feedback="showResponseFeedback"
      />
      <HighlightTextViewer
        v-if="questionType === 'highlight_text'"
        ref="highlightTextViewer"
        :qti-json="JSON.parse(qtiJson)"
        :show-response-feedback="showResponseFeedback"
      />
      <HighlightTableViewer
        v-if="questionType === 'highlight_table'"
        ref="highlightTableViewer"
        :qti-json="JSON.parse(qtiJson)"
        :show-response-feedback="showResponseFeedback"
      />
      <b-button v-show="showSubmit"
                variant="primary"
                :disabled="!submitButtonActive"
                size="sm"
                @click="handleSubmit()"
      >
        Submit
      </b-button>
      <StructureImageUploader v-if="assignmentId && questionId && questionType === 'submit_molecule'"
                              :key="`structure-image-uploader-key-${structureImageUploaderKey}`"
                              :assignment-id="assignmentId"
                              :question-id="questionId"
                              :submit-button-active="submitButtonActive"
                              @setStructureS3Key="setStructureS3Key"
                              @setImageSmiles="setImageSmiles"
                              @updateStructureImageUploaderKey="updateStructureImageUploaderKey"
      />
      <b-button v-if="showResetResponse"
                variant="info"
                size="sm"
                @click="$emit('resetResponse')"
      >
        Reset
      </b-button>
      <div v-if="false" class="pt-2">
        <b-button v-if="jsonShown" size="sm" @click="jsonShown = false">
          Hide json
        </b-button>
        <b-button v-if="!jsonShown" size="sm" @click="jsonShown = true">
          Show json
        </b-button>
      </div>
      <div v-if="jsonShown" class="pt-2">
        <hr>
        {{ question }}
      </div>
    </div>
  </div>
</template>

<script>
import { v4 as uuidv4 } from 'uuid'
import $ from 'jquery'
import { mapGetters } from 'vuex'
import ForgeViewer from './viewers/ForgeViewer.vue'
import SketcherViewer from './viewers/SketcherViewer.vue'
import SingleNumericalViewer from './viewers/SingleNumericalViewer'
import MultiNumericalViewer from './viewers/MultiNumericalViewer'
import BowTieViewer from './viewers/BowTieViewer'
import MatrixMultipleChoiceViewer from './viewers/MatrixMultipleChoiceViewer'
import MultipleResponseSelectAllThatApplyOrSelectNViewer
  from './viewers/MultipleResponseSelectAllThatApplyOrSelectNViewer'
import MultipleResponseGroupingViewer from './viewers/MultipleResponseGroupingViewer'
import DropDownTableViewer from './viewers/DropDownTableViewer'
import DragAndDropClozeViewer from './viewers/DragAndDropClozeViewer'
import HighlightTextViewer from './viewers/HighlightTextViewer'
import HighlightTableViewer from './viewers/HighlightTableViewer'
import FillInTheBlankViewer from './viewers/FillInTheBlankViewer'
import MatchingViewer from './viewers/MatchingViewer'
import SelectChoiceDropDownRationaleViewer from './viewers/SelectChoiceDropDownRationaleViewer'
import MultipleAnswersViewer from './viewers/MultipleAnswersViewer'
import MultipleChoiceTrueFalseViewer from './viewers/MultipleChoiceTrueFalseViewer'
import MatrixMultipleResponseViewer from './viewers/MatrixMultipleResponseViewer'
import { formatQuestionMediaPlayer } from '~/helpers/Questions'
import DiscussItViewer from './viewers/DiscussItViewer.vue'
import StructureImageUploader from './StructureImageUploader.vue'
import ThreeDModelViewer from './viewers/ThreeDModelViewer.vue'
import AccountingJournalEntryViewer from './viewers/AccountingJournalEntryViewer.vue'
import AccountingReportViewer from './viewers/AccountingReportViewer.vue'
import FlashcardViewer from './viewers/FlashcardViewer.vue'
import AccountingMultiPartComputationViewer from './viewers/AccountingMultiPartComputationViewer.vue'
import { escapeDollar } from '../helpers/MathJax'

export default {
  name: 'QtiJsonQuestionViewer',
  components: {
    AccountingMultiPartComputationViewer,
    FlashcardViewer,
    AccountingReportViewer,
    ForgeViewer,
    AccountingJournalEntryViewer,
    StructureImageUploader,
    DiscussItViewer,
    SketcherViewer,
    ThreeDModelViewer,
    MatrixMultipleResponseViewer,
    MultipleChoiceTrueFalseViewer,
    MultipleAnswersViewer,
    SingleNumericalViewer,
    MultiNumericalViewer,
    SelectChoiceDropDownRationaleViewer,
    FillInTheBlankViewer,
    MatchingViewer,
    HighlightTableViewer,
    HighlightTextViewer,
    BowTieViewer,
    MatrixMultipleChoiceViewer,
    MultipleResponseSelectAllThatApplyOrSelectNViewer,
    MultipleResponseGroupingViewer,
    DropDownTableViewer,
    DragAndDropClozeViewer
  },
  props: {
    assessmentType: { type: String, default: '' },
    initialQuestionId: { type: Number, default: 0 },
    allFlashcards: {
      type: Array,
      default: () => []
    },
    flashcardSettings: {
      type: Object,
      default: () => ({
        autoplay: { enabled: false, seconds: 4, student_override: false },
        random_shuffle: { enabled: false, student_override: false },
        show_hint: { enabled: true, student_override: false },
        text_to_speech: { enabled: false, student_override: false },
        captions: { enabled: false, student_override: false }
      })
    },
    qtiJson: {
      type: String,
      default: ''
    },
    studentResponse: {
      type: String,
      default: ''
    },
    showSubmit: {
      type: Boolean,
      default: true
    },
    showResetResponse: {
      type: Boolean,
      default: false
    },
    submitButtonActive: {
      type: Boolean,
      default: true
    },
    showQtiAnswer: {
      type: Boolean,
      default: false
    },
    showResponseFeedback: {
      type: Boolean,
      default: true
    },
    presentationMode: {
      type: Boolean,
      default: false
    },
    previewOrSolution: {
      type: Boolean,
      default: false
    },
    previewingQuestion: {
      type: Boolean,
      default: false
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
      uuid: '',
      receivedModelStructureData: false,
      structureImageUploaderKey: 0,
      confirmedStructure: false,
      structureS3Key: '',
      solutionStructure: '',
      response: '',
      receivedStructure: false,
      clickerApp: window.config.clickerApp,
      qtiJsonCacheKey: 0,
      matchingFeedback: '',
      termsToMatch: [],
      possibleMatches: [],
      jsonShown: false,
      submissionErrorMessage: '',
      questionType: '',
      selectChoices: [],
      question: {},
      prompt: '',
      simpleChoice: [],
      flashcardSubmitQuestionId: null // tracks which card's question_id to submit against
    }
  ),
  computed: {
    isLocalMe: () => window.config.isAdmin && window.location.hostname === 'local.adapt',
    ...mapGetters({
      user: 'auth/user'
    }),
    parsedQtiJson () {
      try {
        return JSON.parse(this.qtiJson)
      } catch (e) {
        return {}
      }
    }
  },
  watch: {
    qtiJson (newValue, oldValue) {
      if (this.questionType === 'flashcard') return
      this.qtiJsonCacheKey++
    }
  },
  created () {
    window.addEventListener('message', this.receiveMessage, false)
  },
  beforeDestroy () {
    window.removeEventListener('message', this.hotKeys)
  },
  mounted () {
    // this.qtiJsonCacheKey = uuidv4()
    this.$nextTick(() => {
      const iframes = document.querySelectorAll('iframe[title="3D Renderer"]')
      iframes.forEach((iframe) => {
        iframe.style.height = '600px'
        iframe.style.width = '100%'
      })
    })

    this.question = JSON.parse(this.qtiJson)
    if (
      !this.question
    ) {
      console.log('no question here')
      return false
    }
    this.questionType = this.question.questionType
    if (this.questionType === 'marker') {
      this.solutionStructure = JSON.stringify(JSON.parse(this.qtiJson).solutionStructure)
      if (this.previewingQuestion) {
        let solutionStructure = JSON.parse(this.qtiJson).solutionStructure
        for (const item of ['atoms', 'bonds']) {
          for (let i = 0; i < solutionStructure[item].length; i++) {
            delete solutionStructure[item][i].mark
          }
        }
        this.solutionStructure = JSON.stringify(solutionStructure)
      }
    }
    switch (this.questionType) {
      case ('flashcard'):
      case ('discuss_it'):
      case ('accounting_journal_entry'):
        break
      case ('accounting_report'):
      case ('accounting_multi_part_computation'):
      case ('forge'):
      case ('forge_iteration'):
      case ('three_d_model_multiple_choice'):
      case ('submit_molecule'):
      case ('marker'):
      case ('matching') :
      case ('multiple_answers'):
      case ('matrix_multiple_response'):
      case ('multiple_response_grouping'):
      case ('drop_down_table'):
      case ('matrix_multiple_choice'):
      case ('drag_and_drop_cloze'):
      case ('highlight_text'):
      case ('multiple_choice'):
      case ('true_false'):
      case ('bow_tie'):
      case ('highlight_table'):
        this.prompt = this.question['prompt']
        this.$nextTick(() => {
          $('#question').find('h2').css({
            'display': 'block',
            'font-size': '1.333em',
            'margin-block-start': '0.83em',
            'margin-block-end': '0.83em',
            'margin-inline-start': '0px',
            'margin-inline-end': '0px',
            'font-weight': 'bold'
          })
        })
        break
      case ('numerical'):
        this.prompt = ''
        this.$nextTick(() => {
          $('#question').find('h2').css({
            'display': 'block',
            'font-size': '1.333em',
            'margin-block-start': '0.83em',
            'margin-block-end': '0.83em',
            'margin-inline-start': '0px',
            'margin-inline-end': '0px',
            'font-weight': 'bold'
          })
        })
        break
      case ('multi_numerical'):
        // MultiNumericalViewer renders the prompt inline — don't set it here
        this.prompt = ''
        this.$nextTick(() => {
          $('#question').find('h2').css({
            'display': 'block',
          })
        })
        break
      case ('drop_down_rationale'):
      case ('select_choice'):
      case ('fill_in_the_blank'):
      case ('drop_down_rationale_triad'):
        break
      case ('multiple_response_select_n'):
      case ('multiple_response_select_all_that_apply'): {
        let reg = /\[([1-9]|[1-9][0-9])\]/g
        let bracketArray = this.question['prompt'].split(reg)
        this.prompt = bracketArray.join(' ')
        break
      }
      default:
        alert(`${this.questionType} is not yet supported.`)
    }
    this.prompt = this.formatQuestionMediaPlayer(this.prompt)
    this.$forceUpdate()
    this.$nextTick(() => {
      this.typesetMath()
      iFrameResize({ log: true }, '.question-media-player')
    })

    console.error('flashcard cards:', this.allFlashcards, JSON.parse(this.qtiJson))
  },
  methods: {
    escapeDollar,
    formatQuestionMediaPlayer,
    waitFor3DModel () {
      return new Promise((resolve) => {
        const intervalId = setInterval(() => {
          if (this.receivedModelStructureData) {
            clearInterval(intervalId)
            resolve()
          } else {
            console.log('Checking for received3DModel...')
          }
        }, 50)
      })
    },
    updateStructureImageUploaderKey () {
      this.structureImageUploaderKey++
    },
    setStructureS3Key (s3Key) {
      this.structureS3Key = s3Key
    },
    setImageSmiles () {
      const iframe = document.getElementById('sketcherViewer')
      iframe.contentWindow.postMessage('export', '*')
    },
    openContactGraderModal (type) {
      this.$emit('openContactGraderModal', type)
    },
    receiveMessage (event) {
      if (event.data.modelStructureData) {
        console.error(event.data.modelStructureData)
        this.response = event.data.modelStructureData
        this.receivedModelStructureData = true
        this.$forceUpdate()
      }
      if (event.data.image_smiles) {
        this.imageSmiles = event.data.image_smiles
      }
      if (event.data.structure) {
        this.response = {
          structure: event.data.structure
        }
        if (event.data.submitted_smiles) {
          this.response.submitted_smiles = event.data.submitted_smiles
        }
        this.receivedStructure = true
        this.$forceUpdate()
      }
    },
    // Called when a student clicks Correct/Incorrect on a flashcard.
    // Stashes the card's question_id so submitResponse() targets the right question,
    // then immediately triggers the normal submit flow.
    onSelfReported ({ result, questionId }) {
      // Sync parent's currentPage to this card's question before submitting,
      // so score updates land on the right question in questions_view.
      this.$emit('cardChanged', questionId)
      this.flashcardSubmitQuestionId = questionId
      this.submitResponse()
    },
    showRandomizedMessage () {
      if (this.presentationMode) {
        return false
      }
      if (!this.showQtiAnswer &&
        this.user.role === 2 &&
        (
          ['drop_down_rationale', 'multiple_answers', 'drop_down_rationale_triad'].includes(this.questionType) ||
          (['multiple_choice', 'select_choice'].includes(this.questionType) && JSON.parse(this.qtiJson).randomizeOrder === 'yes') ||
          (this.questionType === 'select_choice' && !JSON.parse(this.qtiJson).randomizeOrder)
        )
      ) {
        return true
      }
      return false
    },
    shuffleArray (array) {
      for (let i = array.length - 1; i > 0; i--) {
        const j = Math.floor(Math.random() * (i + 1));
        [array[i], array[j]] = [array[j], array[i]]
      }
    },
    waitForStructure () {
      return new Promise((resolve) => {
        const intervalId = setInterval(() => {
          if (this.receivedStructure) {
            clearInterval(intervalId)
            resolve()
          } else {
            console.log('Checking for receivedStructure...')
          }
        }, 50)
      })
    },
    async handleGetStructure () {
      await this.waitForStructure()
      this.message = 'Structure received!'
    },
    handleSubmit () {
      if (!this.confirmedStructure && this.questionType === 'submit_molecule') {
        this.checkIfUploadedWithoutEditing()
      } else {
        this.submitResponse()
      }
    },
    async checkIfUploadedWithoutEditing () {
      if (!this.structureS3Key || (this.response.submitted_smiles !== this.imageSmiles)) {
        await this.submitResponse()
      } else {
        this.confirmedStructure = true
        const iframe = document.getElementById('sketcherViewer')
        iframe.contentWindow.postMessage('save', '*')
        await this.handleGetStructure()
        this.$bvModal.show('modal-confirm-structure')
      }
    },
    async handleGet3DModel () {
      await this.waitFor3DModel()
      this.message = '3D Model received!'
    },
    async submitResponse () {
      let response
      let invalidResponse = false
      let submissionErrorMessage
      switch (this.questionType) {
        case ('flashcard'):
          response = this.$refs.flashcardViewer.getStudentResponse()
          if (!response) {
            // Shouldn't happen since onSelfReported sets it first, but guard anyway
            return false
          }
          break
        case ('accounting_report'):
          response = JSON.stringify(this.$refs.accountingReportViewer.studentResponses)
          break
        case ('accounting_journal_entry'):
          const journalEntryResponse = this.$refs.accountingJournalEntryViewer.getStudentResponse()
          if (!journalEntryResponse) {
            invalidResponse = true
            submissionErrorMessage = 'Please complete all fields before submitting.'
            break
          }
          response = JSON.stringify(journalEntryResponse)
          break
        case ('accounting_multi_part_computation'):
          response = JSON.stringify(this.$refs.accountingMultiPartComputationViewer.getStudentResponse())
          break
        case ('three_d_model_multiple_choice'):
          const markComponentiframe = document.querySelector('#question-to-view .threeDModelViewer')
          markComponentiframe.contentWindow.postMessage('save3DModel', '*')
          await this.handleGet3DModel()
          response = JSON.stringify(this.response)
          break
        case ('submit_molecule'):
        case ('marker'):
          const iframe = document.getElementById('sketcherViewer')
          iframe.contentWindow.postMessage('save', '*')
          await this.handleGetStructure()
          if (this.structureS3Key) {
            this.response.structure_s3_key = this.structureS3Key
          }
          if (this.imageSmiles) {
            this.response.image_smiles = this.imageSmiles
          }
          this.imageSmiles = ''
          this.structureS3Key = ''
          response = JSON.stringify(this.response)
          break
        case ('numerical'):
          response = this.$refs.numericalViewer.numericalResponse.toString().trim()
          if (response === '') {
            invalidResponse = true
            submissionErrorMessage = 'Please enter a number before submitting.'
          } else if (isNaN(response)) {
            invalidResponse = true
            submissionErrorMessage = 'Please enter only a numerical value before submitting.'
          } else {
            response = response.replace(',', '')
          }
          break
        case ('multi_numerical'):
          response = []
          let numericalInvalidFormat = false
          $('#question-to-view input.numerical-blank').each(function () {
            const val = $(this).val().trim()
            if (val === '') {
              $(this).addClass('is-invalid-border')
              invalidResponse = true
            } else if (isNaN(val)) {
              $(this).addClass('is-invalid-border')
              numericalInvalidFormat = true
            }
            response.push({ response: val })
          })
          if (invalidResponse) {
            submissionErrorMessage = 'Please enter a number in all text boxes before submitting.'
          } else if (numericalInvalidFormat) {
            submissionErrorMessage = 'Please enter only numerical values before submitting.'
            invalidResponse = true
          } else {
            response = JSON.stringify({ answers: response })
          }
          break
        case ('matching'):
          response = JSON.stringify(this.$refs.matchingViewer.termsToMatch)
          break
        case ('multiple_choice'):
        case ('true_false'):
          response = this.$refs.multipleChoiceTrueFalseViewer.selectedSimpleChoice
          // need to check client side otherwise a null submission will throw an error with a 422 message
          if ((response === null || response === '')) {
            invalidResponse = true
          }
          submissionErrorMessage = 'Please make a selection before submitting.'
          break
        case ('multiple_answers'):
          response = JSON.stringify(this.$refs.multipleAnswersViewer.selectedMultipleAnswers)
          break
        case ('matrix_multiple_response'):
          response = JSON.stringify(this.$refs.matrixMultipleResponseViewer.selectedMatrixMultipleResponses)
          console.log(response)
          break
        case ('drop_down_rationale'):
        case ('select_choice'):
        case ('drop_down_rationale_triad'):
          response = []
          let chosenOptions = []
          let questionType = this.questionType
          $('#question select.select-choice').each(function () {
            console.log($(this).val())
            if (questionType === 'drop_down_rationale_triad') {
              chosenOptions.push($(this).val())
            }
            if ($(this).val() === '') {
              $(this).addClass('is-invalid-border')
              invalidResponse = true
              submissionErrorMessage = 'Please make a make a selection for all of the dropdowns before submitting.'
            }
            let identifier
            let classes = $(this).attr('class').split(/\s+/)
            for (let i = 0; i < classes.length; i++) {
              if (classes[i].startsWith('identifier-')) {
                identifier = classes[i].replace('identifier-', '')
              }
            }
            if (questionType === 'drop_down_rationale_triad' &&
              chosenOptions.length === 3 &&
              chosenOptions[1] === chosenOptions[2]) {
              invalidResponse = true
              submissionErrorMessage = 'You have chosen the same rationale twice.'
              $('select.select-choice').each(function (index) {
                if (index > 0) {
                  $(this).addClass('is-invalid-border')
                }
              })
            }
            response.push({ identifier: identifier, value: $(this).val() })
          })
          response = JSON.stringify(response)
          break
        case ('fill_in_the_blank'):
          response = []
          $('input.fill-in-the-blank').each(function () {
            if ($(this).val() === '') {
              $(this).addClass('is-invalid-border')
              invalidResponse = true
              submissionErrorMessage = 'Please be sure to fill in all blanks before submitting.'
            }
            response.push({ identifier: $(this).attr('class'), value: $(this).val() })
          })
          response = JSON.stringify(response)
          break
        case ('bow_tie'):
          let actionsToTake = this.$refs.bowTieViewer.selectedActionsToTake
          if (actionsToTake.length !== 2) {
            this.$noty.info('Please select 2 Actions To Take.')
            return false
          }
          let potentialCondition = this.$refs.bowTieViewer.selectedPotentialCondition
          if (potentialCondition.length !== 1) {
            this.$noty.info('Please select 1 of the Potential Conditions.')
            return false
          }
          let parametersToMonitor = this.$refs.bowTieViewer.selectedParametersToMonitor
          if (parametersToMonitor.length !== 2) {
            this.$noty.info('Please select 2 Parameters To Monitor.')
            return false
          }
          response = JSON.stringify({
            actionsToTake: actionsToTake,
            potentialConditions: potentialCondition,
            parametersToMonitor: parametersToMonitor
          })
          console.log(response)
          break
        case ('multiple_response_select_n'):
        case ('multiple_response_select_all_that_apply'):
          response = JSON.stringify(this.$refs.multipleResponseSelectAllThatApplyOrSelectNViewer.selectedAllThatApply)
          break
        case ('multiple_response_grouping'):
          response = JSON.stringify(this.$refs.multipleResponseGroupingViewer.selected)
          break
        case ('drop_down_table'):
          response = JSON.stringify(this.$refs.dropDownTableViewer.selected)
          break
        case ('drag_and_drop_cloze'):
          this.$forceUpdate()
          let notSelecteds = []
          let selecteds = []
          let repeats = []
          $('#question .drop-down-cloze-select').each(function (index) {
            let response = $(this).val()
            if (response) {
              if (selecteds.includes(response)) {
                if (!repeats.includes($(this).text())) {
                  repeats.push($(this).find('option:selected').text())
                }
              }
              selecteds.push(response)
            } else {
              notSelecteds.push(index)
            }
          })
          if (notSelecteds.length) {
            for (let i = 0; i < notSelecteds.length; i++) {
              let message
              let index = notSelecteds[i]
              if (index === 0) {
                message = 'Please make a selection for the 1st dropdown.'
              } else if (index === 1) {
                message = 'Please make a selection for the 2nd dropdown.'
              } else if (index === 2) {
                message = 'Please make a selection for the 3rd dropdown.'
              } else {
                message = `Please make a selection for the ${index}th dropdown.`
              }
              this.$noty.info(message)
            }
            return false
          }
          if (repeats.length) {
            for (let i = 0; i < repeats.length; i++) {
              this.$noty.info(`${repeats[i]} has been chosen multiple times.`)
            }
            return false
          }
          response = JSON.stringify(selecteds)
          break
        case ('matrix_multiple_choice'):
          response = JSON.stringify(this.$refs.matrixMultipleChoiceViewer.selected)
          break
        case ('highlight_table'):
        case ('highlight_text'):
          let responses = []
          $('.selected.response').each(function () {
            responses.push($(this).attr('id'))
          })
          if (!responses.length) {
            this.$noty.info('Please select at least one of the groups of highlighted text.')
            return false
          }
          response = JSON.stringify(responses)
          break
        default:
          alert(`${this.questionType} hasn't been set up as a submission type yet.`)
      }
      if (invalidResponse) {
        this.submissionErrorMessage = submissionErrorMessage.replace('"', '\'\'')
        this.clickerApp
          ? window.parent.postMessage(`{"source": "app_clicker","message": "${this.submissionErrorMessage}","type":"error"}`, '*')
          : this.$bvModal.show('modal-submission-error')
        return false
      }
      console.error(response)
      // For flashcards, emit with the specific card's question_id so the parent
      // can target the correct question in its questions array, then reset the stash.
      const emitData = this.questionType === 'flashcard' && this.flashcardSubmitQuestionId
        ? { data: response, origin: 'qti', question_id: this.flashcardSubmitQuestionId }
        : { data: response, origin: 'qti' }
      this.flashcardSubmitQuestionId = null
      this.$emit('submitResponse', emitData)
    }

  }
}
</script>
<style scoped>
.inline-form-control {
  width: auto;
  display: inline-block;
}

</style>
