<template>
  <span>
    <span v-if="questions[currentPage - 1]">
     <span v-show="false">
       Type: {{ questions[currentPage - 1].solution_type }}<br>
    Render webwork solution:{{ questions[currentPage - 1].render_webwork_solution }}<br>
     </span>
      <b-modal
        :id="`modal-show-html-solution-${modalId}`"
        ref="htmlModal"
        aria-label="Solution"
        size="lg"
        @shown="onHTMLSolutionModalShown"
      >
    <template #modal-header="{ close }">
  <h2 class="editable mb-0">
    {{ getModalTitle(questions[currentPage - 1].solution_html) }}
  </h2>
  <button type="button" aria-label="Close" class="close" @click="close()">×</button>
</template>
        <div v-if="imathasSolution">
          <iframe
            :key="`technology-iframe-${questions[currentPage-1].id}-1`"
            v-resize="{ log: false, checkOrigin: false }"
            width="100%"
            :src="imathasSolutionSrc"
            frameborder="0"
          />
        </div>
        <div v-if="questions[currentPage - 1].render_webwork_solution && !renderedWebworkSolution">
          <div class="d-flex justify-content-center mb-3">
            <div class="text-center">
              <b-spinner variant="primary" label="Text Centered"/>
              <span style="font-size:30px" class="text-primary"> Generating Algorithmic Solution...</span>
            </div>
          </div>
        </div>
        <div v-html="renderedWebworkSolution"/>
        <div v-if="!renderedWebworkSolution && !questions[currentPage - 1].render_webwork_solution"
             v-html="questions[currentPage-1].solution_html"
        />
        <template #modal-footer="{ ok }">
          <b-button size="sm" variant="primary"
                    @click="$bvModal.hide(`modal-show-html-solution-${modalId}`)"
          >
            OK
          </b-button>
        </template>
      </b-modal>
      <b-modal
        :id="`modal-show-audio-solution-${modalId}`"
        ref="modal"
        title="Audio Solution"
        ok-title="OK"
        ok-only
        :size="questions[currentPage-1].solution_text ? 'lg' : 'md'"
      >
        <b-row align-h="center">
          <b-card>
            <audio-player :src="questions[currentPage-1].solution_file_url"/>
          </b-card>
        </b-row>
        <div v-if="questions[currentPage-1].solution_text" class="pt-3">
          <span v-html="questions[currentPage-1].solution_text"/>
        </div>
      </b-modal>
      <span v-if="questions[currentPage-1].solution_type === 'audio'">
          <a href=""
             class="btn btn-outline-primary btn-sm link-outline-primary-btn"
             @click="openShowAudioSolutionModal"
          >{{
              useViewSolutionAsText ? 'View Solution' : standardizeFilename(questions[currentPage - 1].solution)
            }}</a>
      </span>
      <span v-if="questions[currentPage-1].solution_type === 'q'">
          <a :href="questions[currentPage-1].solution_file_url"
             target="_blank"
          >
          {{ useViewSolutionAsText ? 'failed Solution' : standardizeFilename(questions[currentPage - 1].solution) }}
        </a>
      </span>
      <a v-if="questions[currentPage-1].solution_type === 'html'"
         id="view-solution-button"
         href=""
         class="btn btn-outline-primary link-outline-primary-btn"
         :class="`btn-${buttonSize}`"
         v-show="showButton"
         :aria-disabled="loadingSolution"
         @click.prevent="onViewSolutionClicked"
      >
        <b-spinner v-if="loadingSolution" small/>
        {{ loadingSolution ? 'Loading...' : 'View Solution' }}
      </a>
      <span
        v-if="showNa && !questions[currentPage-1].solution_type"
      >N/A</span>
    </span>
  </span>
</template>

<script>
import $ from 'jquery'
import { applyWarningsVisibility } from '../helpers/CSSUpdates'
import { mapGetters } from 'vuex'
import axios from 'axios'

export default {
  props: {
    buttonSize: {
      type: String,
      default: 'sm'
    },
    showButton: {
      type: Boolean,
      default: true
    },
    modalId: {
      type: String,
      default: 'some-id'
    },
    useViewSolutionAsText: {
      type: Boolean,
      default: false
    },
    isPreviewSolutionHtml: {
      type: Boolean,
      default: false
    },
    questions: {
      type: Array,
      default: null
    },
    currentPage: {
      type: Number,
      default: null
    },
    assignmentName: {
      type: String,
      default: 'Assignment'
    },
    showNa: {
      type: Boolean,
      default: true
    },
    formatFilename: {
      type: Boolean,
      default: true
    }
  },
  data: () => ({
    imathasSolution: '',
    imathasSolutionSrc: '',
    renderedWebworkSolution: '',
    technologyIframe: '',
    loadingSolution: false
  }),
  computed: mapGetters({
    user: 'auth/user'
  }),
  created () {
    window.addEventListener('message', this.receiveMessage, false)
  },
  destroyed () {
    window.removeEventListener('message', this.receiveMessage)
    window.removeEventListener('resize', this.onWindowResize)
  },
  mounted () {
    applyWarningsVisibility(this.user)
    try {
      const url = new URL(this.questions[this.currentPage - 1].technology_iframe)
      url.searchParams.delete('sessionJWT')
      this.technologyIframe = url.toString()
    } catch (e) {
    }
  },
  methods: {
    getModalTitle (solutionHtml) {
      return solutionHtml && solutionHtml.includes('<h2 class="editable">Answer</h2>') ? 'Answer' : 'Solution'
    },
    async onViewSolutionClicked () {
      if (this.loadingSolution) return

      if (this.questions[this.currentPage - 1].imathas_solution) {
        return this.getIMathASSolution(this.questions[this.currentPage - 1].problem_jwt)
      }

      if (this.renderedWebworkSolution) {
        return this.openShowHTMLSolutionModal()
      }

      if (this.questions[this.currentPage - 1].render_webwork_solution) {
        this.loadingSolution = true
        try {
          const url = new URL(this.questions[this.currentPage - 1].technology_iframe)
          const problemJWT = url.searchParams.get('problemJWT')
          await this.getWebworkSolution(problemJWT)
        } finally {
          this.loadingSolution = false
        }
      }
      await this.openShowHTMLSolutionModal()
    },
    async getWebworkSolution (problemJWT) {
      try {
        const { data } = await axios.get(`/api/webwork/solution/${problemJWT}`)
        if (data.type === 'error') {
          this.$noty.error(data.message)
          return
        }
        this.renderedWebworkSolution = data.message
      } catch (error) {
        this.$noty.error(error.message)
      }
    },
    async getIMathASSolution (problemJWT) {
      this.imathasSolution = true
      const imathasDomain = ['dev', 'local'].includes(window.config.environment)
        ? 'dev2.imathas.libretexts.org'
        : 'imathas.libretexts.org'
      this.imathasSolutionSrc = `https://${imathasDomain}/imathas/adapt/showdetsoln.php?problemJWT=${problemJWT}`
      await this.openShowHTMLSolutionModal()
    },
    getSolutionModalEl () {
      return document.querySelector(`#modal-show-html-solution-${this.modalId}`)
    },
    async onHTMLSolutionModalShown () {
      const modalEl = this.getSolutionModalEl()
      if (!modalEl) return
      this.convertMathJaxV2ToV3(modalEl)
      // typesetMath may or may not return a promise; await either way so we
      // measure the rendered math, not the raw TeX.
      try {
        await Promise.resolve(this.typesetMath(modalEl))
      } catch (e) {
        console.error(e)
      }
      // Give the browser one frame to lay out the typeset output.
      requestAnimationFrame(() => this.fitModalToContent(modalEl))
      window.removeEventListener('resize', this.onWindowResize)
      window.addEventListener('resize', this.onWindowResize)
    },
    onWindowResize () {
      const modalEl = this.getSolutionModalEl()
      if (modalEl && modalEl.offsetParent !== null) {
        this.fitModalToContent(modalEl)
      }
    },
    // Widens the modal so wide content (MathJax matrices, tables, images)
    // fits, up to 95% of the viewport. Anything wider scrolls horizontally.
    fitModalToContent (modalEl) {
      const dialog = modalEl.querySelector('.modal-dialog')
      const body = modalEl.querySelector('.modal-body')
      if (!dialog || !body) return

      // Reset to the size="lg" default before measuring.
      dialog.style.maxWidth = ''
      body.style.overflowX = ''

      const style = getComputedStyle(body)
      const padding = parseFloat(style.paddingLeft) + parseFloat(style.paddingRight)

      // scrollWidth includes overflowing content, even inside overflow:auto containers.
      let contentWidth = body.scrollWidth
      body.querySelectorAll('mjx-container, .MathJax_Display, .MJXc-display, .MathJax_SVG_Display, table, img, .mt-section > *')
        .forEach(el => {
          contentWidth = Math.max(contentWidth, el.scrollWidth + padding)
        })

      if (contentWidth <= body.clientWidth) return // already fits

      const maxAllowed = window.innerWidth * 0.95
      // +4 accounts for modal-content borders and rounding
      dialog.style.maxWidth = `${Math.ceil(Math.min(contentWidth + 4, maxAllowed))}px`
      body.style.overflowX = 'auto'
    },
    async openShowHTMLSolutionModal () {
      this.$bvModal.show(`modal-show-html-solution-${this.modalId}`)
      this.$nextTick(() => {
        const solutionModal = $(`#modal-show-html-solution-${this.modalId}`)
        if (!solutionModal.length) return
        solutionModal.find('img').each((_, img) => {
          img.style.maxWidth = '100%'
        })

        const mtSection = solutionModal.find('.mt-section')[0]
        if (mtSection) {
          for (const el of mtSection.querySelectorAll('h2.editable')) {
            el.style.display = 'none'
          }
        }
        // Sizing happens in onHTMLSolutionModalShown, after MathJax has rendered.
      })
    },
    standardizeFilename (filename) {
      if (!this.formatFilename) return filename
      if (!filename) return ''
      const ext = filename.slice((Math.max(0, filename.lastIndexOf('.')) || Infinity) + 1)
      const name = this.assignmentName.replace(/[/\\?%*:|"<>]/g, '-')
      return `${name}-${this.currentPage}.${ext}`
    },
    openShowAudioSolutionModal (event) {
      event.preventDefault()
      this.$bvModal.show(`modal-show-audio-solution-${this.modalId}`)
    }
  }
}
</script>

<style>
.MathJax_Display, .MJXc-display, .MathJax_SVG_Display,
mjx-container[display="true"] {
  overflow-x: auto;
  overflow-y: hidden;
  max-width: 100%;
}
</style>
