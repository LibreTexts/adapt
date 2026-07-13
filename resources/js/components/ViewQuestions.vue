<template>
  <div>
    <div class="overflow-auto">
      <b-pagination
        v-if="questionIdsToView.length >1"
        :key="`current-question-${currentQuestion}`"
        v-model="currentQuestion"
        :total-rows="questionIdsToView.length"
        :per-page="1"
        align="center"
        first-number
        last-number
        limit="17"
        @input="viewQuestion(questionIdsToView[currentQuestion-1])"
      />
    </div>
    <div>
      <SolutionFileHtml
        v-if="showSolutionFileHtml"
        :key="`solution-file-html-${question.id}`"
        :questions="[question]"
        :current-page="1"
        :show-na="false"
        assignment-name="Question"
        :modal-id="'preview-question'"
        :is-preview-solution-html="true"
      />
      <iframe v-show="question.non_technology"
              v-resize="{ log: false, checkOrigin: false }"
              style="height: 30px"
              width="100%"
              :src="question.non_technology_iframe_src"
              frameborder="0"
      />
    </div>
    <QtiJsonQuestionViewer
      v-if="question.technology === 'qti'"
      :key="`qti-json-${question.id}`"
      :qti-json="question.qti_json"
      :student-response="question.student_response"
      :show-submit="false"
      :previewing-question="true"
    />
    <div v-if="question.technology_iframe_src && showQuestion">
      <iframe
        :key="`technology-iframe-${question.id}`"
        v-resize="{ log: false, checkOrigin: false }"
        width="100%"
        :src="question.technology_iframe_src"
        frameborder="0"
      />
      <div v-if="question.solution_html && showSolutions" v-html="question.solution_html" />
    </div>
  </div>
</template>

<script>
import axios from 'axios'
import { h5pResizer } from '~/helpers/H5PResizer'
import _ from 'lodash'
import QtiJsonQuestionViewer from './QtiJsonQuestionViewer'
import { h5pOnLoadCssUpdates, webworkOnLoadCssUpdates, applyWarningsVisibility } from '../helpers/CSSUpdates'
import SolutionFileHtml from './SolutionFileHtml.vue'

export default {
  name: 'ViewQuestions',
  components: { SolutionFileHtml, QtiJsonQuestionViewer },
  props: {
    showSolutionFileHTML: {
      type: Boolean,
      default: true
    },
    showSolutions: {
      type: Boolean,
      default: false
    },
    questionIdsToView: {
      type: Array,
      default: function () {
        return []
      }
    },
    questionToView: {
      type: Object,
      default: function () {
        return {}
      }
    }
  },
  data: () => ({
    type: 'View',
    currentQuestion: 1,
    showQuestion: false,
    loadingQuestion: false,
    question: {},
    currentPage: 1
  }),
  created () {
    h5pResizer()
    window.addEventListener('keydown', this.arrowListener)
    window.addEventListener('message', this.receiveMessage)
  },
  destroyed () {
    window.removeEventListener('keydown', this.arrowListener)
    window.removeEventListener('message', this.receiveMessage)
  },
  mounted () {
    applyWarningsVisibility(this.user)
    this.type = this.questionIdsToView.length ? 'View' : 'Preview'
    if (this.questionIdsToView.length) {
      this.viewQuestion(this.questionIdsToView[0])
    }
    if (!_.isEmpty(this.questionToView)) {
      this.showQuestion = false
      this.$bvModal.show(this.modalId)
      this.typesetMath()
      this.loadingQuestion = true
      this.question = this.questionToView
      this.showQuestion = true
      this.loadingQuestion = false
    }
  },
  methods: {
    receiveMessage (event) {
      console.log(this.question.technology)
      if (this.question.technology === 'h5p') {
        if (event.data === '"loaded"' || event.data === 'loaded') {
          event.source.postMessage(JSON.stringify(h5pOnLoadCssUpdates), event.origin)
        }
      }
      // console.error('aaaaaaaa')
      let jsonObj = {}
      try {
        jsonObj = JSON.parse(event.data)
      } catch (e) {
        console.error('Why me???!?@?#')
      }
      if (jsonObj.type === 'webwork.lifecycle.loaded') {
        event.source.postMessage(JSON.stringify(webworkOnLoadCssUpdates), event.origin)
      }
    },
    arrowListener (event) {
      if (event.key === 'ArrowRight') {
        if (!this.questionIdsToView[this.currentQuestion]) {
          return false
        }
        this.currentQuestion++
      }
      if (event.key === 'ArrowLeft' && this.currentQuestion > 1) {
        this.currentQuestion--
      }
      if (this.questionIdsToView[this.currentQuestion - 1]) {
        this.viewQuestion(this.questionIdsToView[this.currentQuestion - 1])
      }
    },
    async viewQuestion (questionId) {
      this.showQuestion = false
      try {
        this.$bvModal.show(this.modalId)
        this.loadingQuestion = true
        const { data } = await axios.get(`/api/questions/${questionId}`)
        if (data.type === 'error') {
          this.$noty.error(data.message)
          this.loadingQuestion = false
          return false
        }
        this.question = data.question
        console.log(this.question)
        this.question.question_id = data.question.id
        this.$emit('questionToViewSet', this.question)
        this.showQuestion = true
        await this.typesetMath()
      } catch (error) {
        this.$noty.error(error.message)
      }
      this.loadingQuestion = false
    }
  }
}
</script>

<style scoped>

</style>
