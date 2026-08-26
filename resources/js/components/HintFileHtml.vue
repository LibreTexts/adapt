<template>
  <span>
    <span v-if="questions[currentPage - 1]">
      <b-modal
        :id="`modal-show-html-hint-${modalId}`"
        ref="htmlModal"
        size="lg"
        @shown="onHTMLHintModalShown"
      ><template #modal-title>
    <h2 class="editable mb-0">Hint</h2>
  </template>
        <div v-if="questions[currentPage - 1].render_webwork_hint && !renderedWebworkHint">
          <div class="d-flex justify-content-center mb-3">
            <div class="text-center">
              <b-spinner variant="primary" label="Text Centered"/>
              <span style="font-size:30px" class="text-primary"> Generating Hint...</span>
            </div>
          </div>
        </div>
        <div v-html="renderedWebworkHint"/>
        <template #modal-footer>
          <b-button size="sm" variant="primary"
                    @click="$bvModal.hide(`modal-show-html-hint-${modalId}`)"
          >
            OK
          </b-button>
        </template>
      </b-modal>
      <a v-if="questions[currentPage-1].render_webwork_hint"
         id="view-hint-button"
         href=""
         class="btn btn-outline-primary link-outline-primary-btn"
         :class="`btn-${buttonSize}`"
         :aria-disabled="loadingHint"
         @click.prevent="onViewHintClicked"
      >
        <b-spinner v-if="loadingHint" small/>
        {{ loadingHint ? 'Loading...' : 'Show Hint' }}
      </a>
    </span>
  </span>
</template>

<script>
import $ from 'jquery'
import axios from 'axios'

export default {
  props: {
    buttonSize: {
      type: String,
      default: 'sm'
    },
    modalId: {
      type: String,
      default: 'some-id'
    },
    questions: {
      type: Array,
      default: null
    },
    currentPage: {
      type: Number,
      default: null
    }
  },
  data: () => ({
    renderedWebworkHint: '',
    loadingHint: false
  }),
  methods: {
    async onViewHintClicked () {
      if (this.loadingHint) return

      if (this.renderedWebworkHint) {
        return this.openShowHTMLHintModal()
      }

      this.loadingHint = true
      try {
        const url = new URL(this.questions[this.currentPage - 1].technology_iframe)
        const problemJWT = url.searchParams.get('problemJWT')
        await this.getWebworkHint(problemJWT)
      } finally {
        this.loadingHint = false
      }
      await this.openShowHTMLHintModal()
    },
    async getWebworkHint (problemJWT) {
      try {
        const { data } = await axios.get(`/api/webwork/hint/${problemJWT}`)
        if (data.type === 'error') {
          this.$noty.error(data.message)
          return
        }
        this.renderedWebworkHint = data.message
      } catch (error) {
        this.$noty.error(error.message)
      }
    },
    onHTMLHintModalShown () {
      const modalEl = document.querySelector(`#modal-show-html-hint-${this.modalId}`)
      this.convertMathJaxV2ToV3(modalEl)
      this.typesetMath(modalEl)
    },
    async openShowHTMLHintModal () {
      this.$bvModal.show(`modal-show-html-hint-${this.modalId}`)
      this.$nextTick(() => {
        const hintModal = $(`#modal-show-html-hint-${this.modalId}`)
        if (!hintModal.length) return
        hintModal.find('img').each((_, img) => {
          img.style.maxWidth = '100%'
        })
      })
    }
  }
}
</script>

<style>
.MathJax_Display, .MJXc-display, .MathJax_SVG_Display {
  overflow-x: auto;
  overflow-y: hidden;
}
</style>
