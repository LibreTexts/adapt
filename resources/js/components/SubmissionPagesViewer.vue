<template>
  <div class="submission-pages-viewer">
    <div class="submission-pages-toolbar d-flex flex-wrap align-items-center">
      <span v-if="isMultiPage" class="mr-2 small font-weight-bold">Page</span>
      <span v-if="isMultiPage" class="mr-3 d-inline-flex flex-wrap" role="group" aria-label="Go to page">
        <button v-for="pageImage in pages"
                :key="`jump-${pageImage.page}`"
                type="button"
                class="page-chip"
                :class="{ active: currentPage === pageImage.page }"
                :aria-current="currentPage === pageImage.page ? 'page' : null"
                :aria-label="`Page ${pageImage.page}${hasMarkup(pageImage.page) ? ', marked up' : ''}`"
                :title="hasMarkup(pageImage.page) ? 'Marked up' : ''"
                @click="goToPage(pageImage)"
        >
          {{ pageImage.page }}<span v-if="hasMarkup(pageImage.page)" class="page-chip-dot"/>
        </button>
      </span>
      <b-button-group v-if="!toolsActive" size="sm" class="mr-3" aria-label="Page size">
        <b-button :pressed="fit === 'page'" variant="outline-secondary" @click="setFit('page')">
          Fit Page
        </b-button>
        <b-button :pressed="fit === 'width'" variant="outline-secondary" @click="setFit('width')">
          Fit Width
        </b-button>
      </b-button-group>
      <b-button-group v-if="currentPageImage" size="sm" class="mr-3" aria-label="Zoom">
        <b-button v-b-tooltip
                  variant="outline-secondary"
                  title="Zoom out (Ctrl + scroll)"
                  aria-label="Zoom out"
                  :disabled="zoom <= zoomSteps[0]"
                  @click="zoomBy(-1)"
        >
          <b-icon icon="zoom-out" aria-hidden="true"/>
        </b-button>
        <b-button v-b-tooltip
                  variant="outline-secondary"
                  class="zoom-level"
                  :title="zoom > 1 ? 'Back to fit' : 'Fits the pane'"
                  :aria-label="zoom > 1 ? `Zoomed to ${zoomLabel}. Back to fit` : 'Fits the pane'"
                  :disabled="zoom <= 1"
                  @click="setZoom(1)"
        >
          {{ zoomLabel }}
        </b-button>
        <b-button v-b-tooltip
                  variant="outline-secondary"
                  title="Zoom in (Ctrl + scroll)"
                  aria-label="Zoom in"
                  :disabled="zoom >= zoomSteps[zoomSteps.length - 1]"
                  @click="zoomBy(1)"
        >
          <b-icon icon="zoom-in" aria-hidden="true"/>
        </b-button>
      </b-button-group>
      <span v-if="currentPageImage" class="ml-auto d-inline-flex align-items-center small">
        <span v-if="hasAnyChanges" class="mr-3 d-inline-flex align-items-center">
          <b-form-checkbox :checked="showOriginal"
                           switch
                           @change="setShowOriginal($event)"
          >
            Original
          </b-form-checkbox>
          <span class="ml-2" style="position: relative; top: -1px;">
            <QuestionCircleTooltip :id="originalTooltipId"/>
          </span>
          <b-tooltip :target="originalTooltipId"
                     triggers="hover focus"
                     delay="250"
          >
            Shows the pages as the student submitted them, without markup or turning. Nothing is deleted, and
            switching it off brings the markup back.
          </b-tooltip>
        </span>
        <template v-if="canAnnotate">
          <span v-if="!annotationsLoaded">
            <b-spinner v-if="!annotationsError" small class="mr-1"/>
            {{ annotationsError ? 'Markup unavailable' : 'Loading markup…' }}
          </span>
          <b-form-checkbox v-else
                           :checked="showTools && !showOriginal"
                           switch
                           @change="setShowTools($event)"
          >
            Markup tools
          </b-form-checkbox>
        </template>
      </span>
    </div>
    <div v-show="!toolsActive"
         ref="scrollArea"
         class="submission-pages-scroll"
         @scroll="onScroll"
         @wheel="viewWheel"
    >
      <div v-for="pageImage in pages"
           :key="`page-${pageImage.page}-${pageImage.url}`"
           :ref="`page-${pageImage.page}`"
           class="submission-page"
      >
        <div v-if="isMultiPage" class="submission-page-label small text-muted">
          Page {{ pageImage.page }}<span v-if="hasMarkup(pageImage.page) && !showOriginal"> · marked up</span>
        </div>
        <div :ref="`view-${pageImage.page}`"
             class="submission-page-view"
             :class="fit === 'page' && viewZoom === 1 ? 'zoom-in' : 'zoom-out'"
             :title="viewZoom > 1 ? 'Click to fit' : (fit === 'page' ? 'Click to fit width' : 'Click to fit page')"
             @click="toggleFit(pageImage.page)"
        />
      </div>
    </div>

    <div v-if="toolsActive" class="submission-page-tools">
        <div class="d-flex flex-wrap align-items-center mb-2">
          <b-button-group size="sm" class="mr-3 mb-1">
            <b-button v-b-tooltip
                      :pressed="tool === 'select'"
                      variant="outline-secondary"
                      title="Select: click a mark, or drag a box around several"
                      aria-label="Select marks"
                      @click="useTool('select')"
            >
              <b-icon icon="cursor" aria-hidden="true"/>
            </b-button>
            <b-button v-b-tooltip
                      :pressed="tool === 'ArrowMarker'"
                      variant="outline-secondary"
                      title="Arrow"
                      aria-label="Arrow"
                      @click="useTool('ArrowMarker')"
            >
              <b-icon icon="arrow-up-right" aria-hidden="true"/>
            </b-button>
            <b-button v-b-tooltip
                      :pressed="tool === 'FreehandMarker'"
                      variant="outline-secondary"
                      title="Draw"
                      aria-label="Draw"
                      @click="useTool('FreehandMarker')"
            >
              <b-icon icon="pencil" aria-hidden="true"/>
            </b-button>
            <b-button v-b-tooltip
                      :pressed="tool === 'TextMarker'"
                      variant="outline-secondary"
                      title="Text"
                      aria-label="Text"
                      @click="useTool('TextMarker')"
            >
              <b-icon icon="type" aria-hidden="true"/>
            </b-button>
            <b-dropdown ref="stampMenu"
                        size="sm"
                        variant="outline-secondary"
                        :toggle-class="{ active: tool === 'stamp' }"
                        :toggle-attrs="{ 'aria-label': 'Stamps' }"
                        class="stamp-dropdown"
            >
              <template #button-content>
                <span v-b-tooltip title="Stamps: check marks and emoji">
                  <span v-if="tool === 'stamp'" class="stamp-glyph" :style="stampStyle(currentStamp)">
                    {{ currentStamp.glyph }}
                  </span>
                  <b-icon v-else icon="emoji-smile" aria-hidden="true"/>
                </span>
              </template>
              <div class="stamp-grid" role="group" aria-label="Stamps">
                <button v-for="stamp in stamps"
                        :key="stamp.id"
                        type="button"
                        class="stamp-choice"
                        :class="{ active: tool === 'stamp' && currentStamp.id === stamp.id }"
                        :title="stamp.name"
                        :aria-label="stamp.name"
                        :aria-pressed="tool === 'stamp' && currentStamp.id === stamp.id ? 'true' : 'false'"
                        @click="useStamp(stamp.id)"
                >
                  <span class="stamp-glyph" :style="stampStyle(stamp)">{{ stamp.glyph }}</span>
                </button>
              </div>
            </b-dropdown>
          </b-button-group>
          <span class="mr-3 mb-1 d-inline-flex align-items-center">
            <button v-for="swatch in colors"
                    :key="swatch.value"
                    type="button"
                    class="color-swatch"
                    :class="{ active: color === swatch.value }"
                    :style="{ backgroundColor: swatch.value }"
                    :aria-label="`${swatch.name} ink`"
                    :title="swatch.name"
                    @click="useColor(swatch.value)"
            />
          </span>
          <b-button-group size="sm" class="mr-3 mb-1" aria-label="Size">
            <b-button v-for="option in sizes"
                      :key="option.value"
                      :pressed="size === option.value"
                      variant="outline-secondary"
                      :title="`${option.name} text and lines`"
                      @click="useSize(option.value)"
            >
              {{ option.label }}
            </b-button>
          </b-button-group>
          <b-button-group size="sm" class="mr-3 mb-1" aria-label="Rotate page">
            <b-button v-b-tooltip
                      variant="outline-secondary"
                      title="Rotate left"
                      aria-label="Rotate left"
                      @click="rotateEditor(-90)"
            >
              <b-icon icon="arrow-90deg-left" aria-hidden="true"/>
            </b-button>
            <b-button v-b-tooltip
                      variant="outline-secondary"
                      title="Rotate right"
                      aria-label="Rotate right"
                      @click="rotateEditor(90)"
            >
              <b-icon icon="arrow-90deg-right" aria-hidden="true"/>
            </b-button>
          </b-button-group>
          <b-button-group size="sm" class="mb-1">
            <b-button v-b-tooltip
                      variant="outline-secondary"
                      title="Undo"
                      aria-label="Undo"
                      :disabled="!canUndo"
                      @click="undo"
            >
              <b-icon icon="arrow-counterclockwise" aria-hidden="true"/>
            </b-button>
            <b-button v-b-tooltip
                      variant="outline-secondary"
                      title="Redo"
                      aria-label="Redo"
                      :disabled="!canRedo"
                      @click="redo"
            >
              <b-icon icon="arrow-clockwise" aria-hidden="true"/>
            </b-button>
            <b-button v-b-tooltip
                      variant="outline-danger"
                      title="Delete selected (or press Delete)"
                      aria-label="Delete selected"
                      :disabled="!selectedCount"
                      @click="deleteSelected"
            >
              <b-icon icon="trash" aria-hidden="true"/>
            </b-button>
            <b-button v-if="!isMultiPage"
                      v-b-tooltip
                      variant="outline-danger"
                      title="Clear all markup and start again"
                      aria-label="Clear all markup"
                      :disabled="!editorHasMarks"
                      @click="clearMarkup(false)"
            >
              <b-icon icon="x-square" aria-hidden="true"/>
            </b-button>
            <b-dropdown v-else
                        size="sm"
                        variant="outline-danger"
                        right
                        :disabled="!editorHasMarks && !otherPagesHaveMarks"
                        :toggle-attrs="{ 'aria-label': 'Clear markup' }"
            >
              <template #button-content>
                <span v-b-tooltip title="Clear markup and start again">
                  <b-icon icon="x-square" aria-hidden="true"/>
                </span>
              </template>
              <b-dropdown-item-button :disabled="!editorHasMarks" @click="clearMarkup(false)">
                Clear this page
              </b-dropdown-item-button>
              <b-dropdown-item-button @click="clearMarkup(true)">
                Clear all pages
              </b-dropdown-item-button>
            </b-dropdown>
          </b-button-group>
        </div>
        <div class="small text-muted mb-1">
          {{ toolHint }}<span v-if="editorZoom > 1"> To move around, scroll, or hold Alt and drag.</span>
        </div>
      <!-- keeps its height when empty, so the page doesn't jump down when "Saved" first shows -->
      <div class="small mb-1 editor-save-status" aria-live="polite">
        <span v-if="editorSaveStatus === 'saving'" class="text-muted">
          <b-spinner small type="grow"/> Saving…
        </span>
        <span v-else-if="editorSaveStatus === 'failed'" class="text-danger">
          Not saved.
          <a href="" @click.prevent="saveEditor()">Try again</a>
        </span>
        <span v-else-if="editorSaveStatus === 'saved'" class="text-muted">
          <b-icon icon="check" aria-hidden="true"/> Saved
        </span>
      </div>
      <!-- typing in the markup stays out of the Open Grader's keyboard shortcuts -->
      <div ref="editorContainer"
           class="submission-page-editor"
           @pointerdown.capture="editorPointerDown"
           @wheel.capture="editorWheel"
           @keydown.stop="editorKeyDown"
      />
    </div>
  </div>
</template>

<script>
import axios from 'axios'
import { MarkerArea, MarkerView } from '@markerjs/markerjs3'

/**
 * Shows a student's submission pages as images, with any grader markup drawn on top, and lets graders mark up
 * a page with arrows, freehand drawing and text (marker.js 3), with the tools right on the page. Markup saves itself.
 *
 * Markup is stored per assignment, question, student and page, at the page image's natural size, so it lines up
 * at any display width. It's saved as data and drawn over the untouched page image; nothing is burned in.
 * A page can also be turned: state.rotation (0, 90, 180 or 270, clockwise) is saved with the markup, whose coordinates
 * are always in the turned page's space. The image itself is only turned with CSS, so its file is never changed.
 * Because nothing is burned in, "Original" can always show the pages exactly as the student submitted them.
 *
 * Zoom is a multiple of the fitted size (1 = fits). Reading pages, it enlarges the page images and the pane scrolls;
 * marking up, it's marker.js's zoom level relative to the level that fits the page in the editor.
 */
// stamps are marker.js image markers: the check and X take the ink color; emoji keep their own colors
// (praise first, then "not quite", then flags; nothing that reads as the grader being upset with the student)
const EMOJI_STAMPS = [
  { id: 'thumbs-up', glyph: '👍', name: 'Thumbs up' },
  { id: 'star', glyph: '⭐', name: 'Star' },
  { id: 'hundred', glyph: '💯', name: '100' },
  { id: 'heart', glyph: '❤️', name: 'Love it' },
  { id: 'party', glyph: '🎉', name: 'Celebrate' },
  { id: 'smile', glyph: '😊', name: 'Smile' },
  { id: 'thumbs-down', glyph: '👎', name: 'Thumbs down' },
  { id: 'disappointed', glyph: '😞', name: 'Not quite' },
  { id: 'idea', glyph: '💡', name: 'Good idea' },
  { id: 'question', glyph: '❓', name: 'Question' },
  { id: 'warning', glyph: '⚠️', name: 'Careful' },
  { id: 'important', glyph: '‼️', name: 'Important' }
]
const TOOLS = ['select', 'ArrowMarker', 'FreehandMarker', 'TextMarker', 'stamp']
export default {
  name: 'SubmissionPagesViewer',
  props: {
    pages: { type: Array, required: true }, // [{ page, url, width, height }]
    assignmentId: { type: Number, required: true },
    questionId: { type: Number, required: true },
    studentUserId: { type: Number, required: true },
    canAnnotate: { type: Boolean, default: false },
    // markup already fetched by the parent (the student view); when given, nothing is loaded from the server
    initialAnnotations: { type: Object, default: null }
  },
  data: () => ({
    annotations: {}, // page => AnnotationState
    // Mark Up stays disabled until the saved markup has loaded: opening the editor before then would start from a
    // blank page, and saving would overwrite the markup that's already there
    annotationsLoaded: false,
    resizeObserver: null,
    paneSize: { width: 0, height: 0 },
    pendingRender: false,
    annotationsError: false,
    currentPage: 1,
    fit: 'page', // 'page': whole page visible; 'width': page fills the pane's width
    scrollTicking: false,
    showTools: false,
    editingPage: null,
    editorTouched: false,
    editorSaveStatus: '',
    editorSaveTimer: null,
    editorSavePromise: null,
    editorResizeObserver: null,
    editorState: null, // the page and markup after turning it in the editor; not saved until Save Markup
    editorRotation: 0,
    markerArea: null,
    tool: 'select',
    color: '#d0021b',
    colors: [
      { name: 'Red', value: '#d0021b' },
      { name: 'Blue', value: '#1f5fd1' },
      { name: 'Green', value: '#1e8a3c' },
      { name: 'Black', value: '#111111' }
    ],
    size: 'medium',
    sizes: [
      { label: 'S', name: 'Small', value: 'small', factor: 0.6 },
      { label: 'M', name: 'Medium', value: 'medium', factor: 1 },
      { label: 'L', name: 'Large', value: 'large', factor: 1.6 }
    ],
    imageSize: { width: 1000, height: 1300 }, // natural size of the page being marked up
    canUndo: false,
    canRedo: false,
    selectedCount: 0,
    editorHasMarks: false,
    stampId: 'check',
    stamps: [
      { id: 'check', glyph: '✓', name: 'Check mark', marker: 'CheckImageMarker', inked: true },
      { id: 'x', glyph: '✗', name: 'X mark', marker: 'XImageMarker', inked: true },
      ...EMOJI_STAMPS.map(stamp => ({ ...stamp, marker: 'CustomImageMarker', inked: false }))
    ],
    showOriginal: false,
    zoomSteps: [1, 1.5, 2, 3, 4],
    viewZoom: 1,
    editorZoom: 1,
    lastWheelZoom: 0
  }),
  computed: {
    // a single photo or one-page PDF doesn't need page numbers
    isMultiPage () {
      return this.pages.length > 1
    },
    currentPageImage () {
      return this.pages.find(item => item.page === this.currentPage) || this.pages[0] || null
    },
    toolHint () {
      switch (this.tool) {
        case 'ArrowMarker':
          return 'Drag from where the arrow starts to where it points.'
        case 'FreehandMarker':
          return 'Hold the mouse button down and draw.'
        case 'TextMarker':
          return 'Click where the text should go, then type. Click outside the text when you\'re done.'
        case 'stamp':
          return `Click to place ${this.currentStamp.name.toLowerCase()} stamps. Click a stamp to select it, then move, resize or delete it.`
        default:
          return 'Click a mark to select it, or drag a box around several. Then press Delete or click the trash can, ' +
            'or drag to move them. Double-click text to edit it.'
      }
    },
    toolsActive () {
      return this.canAnnotate && this.showTools && !this.showOriginal && this.annotationsLoaded &&
        Boolean(this.currentPageImage)
    },
    currentStamp () {
      return this.stamps.find(stamp => stamp.id === this.stampId) || this.stamps[0]
    },
    // whether "Original" would look any different
    hasAnyChanges () {
      return Object.values(this.annotations).some(state => state &&
        ((state.markers && state.markers.length) || +state.rotation))
    },
    otherPagesHaveMarks () {
      return this.pages.some(pageImage => pageImage.page !== (this.editingPage && this.editingPage.page) &&
        this.hasMarkup(pageImage.page))
    },
    zoom () {
      return this.toolsActive ? this.editorZoom : this.viewZoom
    },
    zoomLabel () {
      return this.zoom > 1 ? `${Math.round(this.zoom * 100)}%` : 'Fit'
    },
    // one viewer per student, but several can be on a page; the tooltip needs an id of its own
    originalTooltipId () {
      return `original-tooltip-${this._uid}`
    },
    annotationsUrl () {
      return `/api/submission-page-annotations/assignment/${this.assignmentId}/question/${this.questionId}/student/${this.studentUserId}`
    }
  },
  watch: {
    // a button that becomes disabled under the pointer never gets the "pointer left" event, so its tooltip
    // would stay up (clicking Undo until there's nothing left to undo, say)
    canUndo () {
      this.hideTooltips()
    },
    canRedo () {
      this.hideTooltips()
    },
    selectedCount () {
      this.hideTooltips()
    },
    editorHasMarks () {
      this.hideTooltips()
    },
    zoom () {
      this.hideTooltips()
    },
    async pages () {
      await this.closeEditor()
      this.showOriginal = false // a new student starts with the markup showing
      this.currentPage = this.pages.length ? this.pages[0].page : 1
      this.$nextTick(() => {
        this.renderAllViews()
        this.openEditor()
      })
    }
  },
  async mounted () {
    this.currentPage = this.pages.length ? this.pages[0].page : 1
    try {
      // remember the grader's choice between students; browser storage can be unavailable
      const savedFit = window.localStorage.getItem('submissionPagesFit')
      if (['page', 'width'].includes(savedFit)) {
        this.fit = savedFit
      }
      // the markup tools stay on from student to student until the grader turns them off
      this.showTools = this.canAnnotate && window.localStorage.getItem('submissionPagesMarkupTools') === '1'
      this.loadMarkupSettings()
    } catch (error) {
    }
    window.addEventListener('resize', this.renderAllViews)
    // Redraw when the pane's size changes, including going from hidden (0 wide) to shown: pages drawn while
    // hidden would otherwise be sized for nothing.
    if (window.ResizeObserver && this.$refs.scrollArea) {
      this.resizeObserver = new window.ResizeObserver(entries => {
        const width = Math.round(entries[0].contentRect.width)
        const height = Math.round(entries[0].contentRect.height)
        if (!width) {
          return
        }
        const changed = width !== this.paneSize.width || height !== this.paneSize.height
        const measuredBefore = this.paneSize.width > 0
        this.paneSize = { width, height }
        // pendingRender: pages were skipped while hidden. Otherwise only a real resize redraws (the first
        // measurement of a visible pane happens right after mounted() already drew the pages).
        if (this.pendingRender || (changed && measuredBefore)) {
          this.pendingRender = false
          this.renderAllViews()
        }
      })
      this.resizeObserver.observe(this.$refs.scrollArea)
    }
    if (this.initialAnnotations) {
      this.annotations = this.cleanAnnotations(this.initialAnnotations)
      this.annotationsLoaded = true
    } else {
      await this.loadAnnotations()
    }
    this.renderAllViews()
    this.openEditor()
  },
  beforeDestroy () {
    this.saveEditor() // moving to another student: keeps what's still waiting to save
    if (this.editorResizeObserver) {
      this.editorResizeObserver.disconnect()
    }
    window.removeEventListener('resize', this.renderAllViews)
    if (this.resizeObserver) {
      this.resizeObserver.disconnect()
    }
  },
  methods: {
    pageRotation (page) {
      const state = this.annotations[page]
      return state ? +state.rotation || 0 : 0
    },
    rotatedSize (size, rotation) {
      return rotation % 180 ? { width: size.height, height: size.width } : size
    },
    // Turns a saved page and its markup by 90 or -90 degrees (clockwise when positive). The marks turn with the page.
    rotateState (state, degrees) {
      const { width, height } = state
      const turn = degrees > 0
        ? (x, y) => ({ x: height - y, y: x })
        : (x, y) => ({ x: y, y: width - x })
      const markers = (state.markers || []).map(marker => {
        const turned = JSON.parse(JSON.stringify(marker))
        if ('x1' in marker) {
          const start = turn(marker.x1, marker.y1)
          const end = turn(marker.x2, marker.y2)
          Object.assign(turned, { x1: start.x, y1: start.y, x2: end.x, y2: end.y })
        }
        if (Array.isArray(marker.points)) {
          turned.points = marker.points.map(point => turn(point.x, point.y))
        }
        if ('left' in marker) {
          const center = turn(marker.left + marker.width / 2, marker.top + marker.height / 2)
          turned.left = center.x - marker.width / 2
          turned.top = center.y - marker.height / 2
          turned.rotationAngle = ((marker.rotationAngle || 0) + degrees + 360) % 360
          delete turned.visualTransformMatrix
          delete turned.containerTransformMatrix
        }
        return turned
      })
      return {
        ...state,
        width: height,
        height: width,
        markers,
        rotation: ((+state.rotation || 0) + degrees + 360) % 360
      }
    },
    // marker.js sizes its image for the turned page; show the image turned inside that same space
    turnMarkerImage (element, rotation, width, height) {
      const img = element.shadowRoot && element.shadowRoot.querySelector('img')
      if (!rotation || !img) {
        return
      }
      const imageWidth = rotation % 180 ? height : width
      const imageHeight = rotation % 180 ? width : height
      const turn = () => {
        img.style.width = `${imageWidth}px`
        img.style.height = `${imageHeight}px`
        img.style.margin = `${(height - imageHeight) / 2}px ${(width - imageWidth) / 2}px`
        img.style.justifySelf = 'start'
        img.style.alignSelf = 'start'
        img.style.transform = `rotate(${rotation}deg)`
        // a turned image would otherwise be drawn over the markup layer that follows it
        if (img.nextElementSibling) {
          img.nextElementSibling.style.position = 'relative'
          img.nextElementSibling.style.zIndex = '1'
        }
      }
      turn()
      img.addEventListener('load', turn)
    },
    // A text box with nothing typed in it (or only marker.js's "Text" placeholder) isn't kept.
    isEmptyNote (marker) {
      return marker.typeName === 'TextMarker' &&
        (typeof marker.text !== 'string' || !marker.text.trim() || marker.text.trim() === 'Text')
    },
    // Laravel turns empty strings in a request into null, so markup saved with an empty field comes back with null,
    // which marker.js can't draw ("NaN" and "reading 'split'" errors). Put the empty strings back, and drop empty notes.
    cleanState (state) {
      if (!state || !Array.isArray(state.markers)) {
        return state
      }
      const markers = state.markers
        .filter(marker => marker && !this.isEmptyNote(marker))
        .map(marker => {
          const cleaned = { ...marker }
          ;['notes', 'strokeDasharray', 'fontFamily'].forEach(key => {
            if (cleaned[key] === null) {
              cleaned[key] = key === 'fontFamily' ? 'Helvetica, Arial, sans-serif' : ''
            }
          })
          return cleaned
        })
      return { ...state, markers }
    },
    cleanAnnotations (annotations) {
      const cleaned = {}
      Object.keys(annotations || {}).forEach(page => {
        cleaned[page] = annotations[page] ? this.cleanState(annotations[page]) : annotations[page]
      })
      return cleaned
    },
    // marker.js can take a text box off the page but keep it in its list of marks; deleting or saving it then
    // fails ("removeChild ... not a child of this node"). Forget those: they're no longer on the page anyway.
    pruneDetachedMarks () {
      const markerArea = this.markerArea
      if (!markerArea || !Array.isArray(markerArea.editors)) {
        return
      }
      const detached = markerArea.editors.filter(editor => editor.container && !editor.container.parentNode)
      detached.forEach(editor => {
        markerArea.editors.splice(markerArea.editors.indexOf(editor), 1)
        const selected = markerArea.selectedMarkerEditors
        if (Array.isArray(selected) && selected.includes(editor)) {
          selected.splice(selected.indexOf(editor), 1)
        }
      })
    },
    // takes empty notes off the page once they're no longer being typed in
    removeEmptyNotes () {
      const markerArea = this.markerArea
      if (!markerArea) {
        return
      }
      this.pruneDetachedMarks()
      // only boxes the grader has clicked away from: one still selected may be about to be typed in
      const selected = markerArea.selectedMarkerEditors || []
      markerArea.editors
        .filter(editor => !['new', 'creating', 'edit'].includes(editor.state) && !selected.includes(editor) &&
          this.isEmptyNote({ typeName: this.markerType(editor), text: editor.marker.text }))
        .forEach(editor => markerArea.deleteMarker(editor, true))
    },
    hasMarkup (page) {
      const state = this.annotations[page]
      return Boolean(state && state.markers && state.markers.length)
    },
    async loadAnnotations () {
      try {
        const { data } = await axios.get(this.annotationsUrl)
        if (data.type === 'error') {
          this.annotationsError = true
          this.$noty.error(data.message)
          return
        }
        this.annotations = this.cleanAnnotations(data.annotations || {})
        this.annotationsLoaded = true
      } catch (error) {
        this.annotationsError = true
        this.$noty.error(error.message)
      }
    },
    renderAllViews () {
      return Promise.all(this.pages.map(pageImage => this.renderView(pageImage)))
    },
    pageElement (page) {
      const refs = this.$refs[`page-${page}`]
      return Array.isArray(refs) ? refs[0] : refs
    },
    // The page whose top has scrolled past 40% of the pane is the one being looked at.
    onScroll () {
      if (this.scrollTicking) {
        return
      }
      this.scrollTicking = true
      window.requestAnimationFrame(() => {
        this.scrollTicking = false
        const scrollArea = this.$refs.scrollArea
        if (!scrollArea) {
          return
        }
        const line = scrollArea.scrollTop + scrollArea.clientHeight * 0.4
        let current = this.pages.length ? this.pages[0].page : 1
        for (const pageImage of this.pages) {
          const element = this.pageElement(pageImage.page)
          if (element && element.offsetTop <= line) {
            current = pageImage.page
          }
        }
        this.currentPage = current
      })
    },
    jumpToPage (page, smooth = false) {
      const scrollArea = this.$refs.scrollArea
      const element = this.pageElement(page)
      if (!scrollArea || !element) {
        return
      }
      this.currentPage = page
      scrollArea.scrollTo({ top: element.offsetTop, behavior: smooth ? 'smooth' : 'auto' })
    },
    async setFit (fit) {
      if (this.fit === fit && this.viewZoom === 1) {
        return
      }
      const page = this.currentPage
      this.fit = fit
      this.viewZoom = 1
      try {
        window.localStorage.setItem('submissionPagesFit', fit)
      } catch (error) {
      }
      await this.renderAllViews()
      this.jumpToPage(page)
    },
    // clicking a page switches between fitting the page and the width; when zoomed in, it goes back to fit
    toggleFit (page) {
      this.currentPage = page
      if (this.viewZoom > 1) {
        this.setZoom(1)
        return
      }
      this.setFit(this.fit === 'page' ? 'width' : 'page')
    },
    zoomBy (direction) {
      const steps = this.zoomSteps
      let index = steps.findIndex(step => step >= this.zoom - 0.001)
      index = index < 0 ? steps.length - 1 : index
      this.setZoom(steps[Math.min(steps.length - 1, Math.max(0, index + direction))])
    },
    async setZoom (zoom) {
      if (this.toolsActive) {
        this.editorZoom = zoom
        this.applyEditorZoom()
        return
      }
      if (this.viewZoom === zoom) {
        return
      }
      const page = this.currentPage
      this.viewZoom = zoom
      await this.renderAllViews()
      this.jumpToPage(page)
    },
    // Ctrl + scroll (and a trackpad pinch, which browsers report the same way) zooms instead of zooming the browser
    wheelZoom (event) {
      if (!event.ctrlKey && !event.metaKey) {
        return false
      }
      event.preventDefault()
      event.stopPropagation()
      const now = Date.now()
      if (now - this.lastWheelZoom > 200) { // a pinch sends many events; take one step at a time
        this.lastWheelZoom = now
        this.zoomBy(event.deltaY < 0 ? 1 : -1)
      }
      return true
    },
    viewWheel (event) {
      this.wheelZoom(event)
    },
    // marker.js moves the page with the scroll wheel. That's how to get around a zoomed-in page, but at fit it just
    // slid the page out of view, so then the wheel is kept from it.
    editorWheel (event) {
      if (this.wheelZoom(event)) {
        return
      }
      if (this.editorZoom <= 1) {
        event.stopPropagation()
      }
    },
    // the zoom level at which the whole page fits in the editor (never enlarged past its own size)
    editorFitZoom () {
      const markerArea = this.markerArea
      const width = markerArea.clientWidth
      const height = markerArea.clientHeight
      if (!width || !height || !markerArea.targetWidth || !markerArea.targetHeight) {
        return markerArea.zoomLevel
      }
      return Math.min(1, width / markerArea.targetWidth, height / markerArea.targetHeight)
    },
    applyEditorZoom () {
      const markerArea = this.markerArea
      if (!markerArea) {
        return
      }
      const fit = this.editorFitZoom()
      if (this.editorZoom === 1 && fit < 1 && typeof markerArea.autoZoom === 'function') {
        markerArea.autoZoom() // also brings the page back to the middle
        return
      }
      markerArea.zoomLevel = fit * this.editorZoom
    },
    async setShowOriginal (show) {
      if (show === this.showOriginal) {
        return
      }
      if (show) {
        await this.closeEditor() // saves anything still waiting
      }
      this.showOriginal = show
      await this.$nextTick()
      if (this.toolsActive) {
        this.openEditor()
        return
      }
      await this.renderAllViews()
      this.jumpToPage(this.currentPage)
    },
    // How big to draw a page: the pane's width, or small enough that the whole page fits in the pane.
    displaySize (container, size) {
      const scrollArea = this.$refs.scrollArea
      const availableWidth = Math.max(100, container.clientWidth || size.width)
      let width = availableWidth
      if (this.fit === 'page' && scrollArea) {
        const availableHeight = Math.max(200, scrollArea.clientHeight - (this.isMultiPage ? 40 : 18)) // room for the page label
        width = Math.min(availableWidth, availableHeight * size.width / size.height)
      }
      width = Math.round(width * this.viewZoom)
      return { width, height: Math.round(width * size.height / size.width) }
    },
    // Read-only display: a plain image, or a marker.js view when the page has markup.
    async renderView (pageImage) {
      const refs = this.$refs[`view-${pageImage.page}`]
      const container = Array.isArray(refs) ? refs[0] : refs
      if (!container) {
        return
      }
      if (!container.clientWidth && this.resizeObserver) {
        this.pendingRender = true // hidden: draw when the observer sees a real size
        return
      }
      while (container.firstChild) {
        container.removeChild(container.firstChild)
      }
      // a resize can start another render while this one waits for the image; only the latest one draws
      const renderToken = String(Date.now() + Math.random())
      container.dataset.renderToken = renderToken
      const img = document.createElement('img')
      img.src = pageImage.url
      img.alt = `Page ${pageImage.page} of the student's submission`
      img.addEventListener('error', () => this.$emit('imageError'), { once: true })

      const size = await this.naturalSize(img, pageImage)
      if (container.dataset.renderToken !== renderToken) {
        return
      }
      const rotation = this.showOriginal ? 0 : this.pageRotation(pageImage.page)
      const { width, height } = this.displaySize(container, this.rotatedSize(size, rotation))
      if (this.showOriginal || (!this.hasMarkup(pageImage.page) && !rotation)) {
        img.className = 'submission-page-image'
        img.style.width = `${width}px`
        img.style.height = `${height}px`
        container.appendChild(img)
        return
      }
      const view = new MarkerView()
      view.targetImage = img
      view.targetWidth = width
      view.targetHeight = height
      // the element doesn't size itself to the target
      view.style.width = `${width}px`
      view.style.height = `${height}px`
      if (!container.dataset.wheelGuard) {
        // marker.js's view swallows the mouse wheel; let it scroll the page list instead
        container.addEventListener('wheel', event => event.stopPropagation(), { capture: true })
        container.dataset.wheelGuard = '1'
      }
      container.appendChild(view)
      // markers are rescaled from the page's natural size to this display size
      view.show(this.annotations[pageImage.page])
      this.turnMarkerImage(view, rotation, width, height)
    },
    async setShowTools (show) {
      const wasOriginal = this.showOriginal
      if (show) {
        this.showOriginal = false // marking up happens on top of the markup
      }
      if (show === this.showTools && !wasOriginal) {
        return
      }
      this.showTools = show
      try {
        window.localStorage.setItem('submissionPagesMarkupTools', show ? '1' : '0')
      } catch (error) {
      }
      if (show) {
        await this.$nextTick()
        this.openEditor()
        return
      }
      await this.closeEditor()
      await this.$nextTick()
      await this.renderAllViews()
      this.jumpToPage(this.currentPage)
    },
    async goToPage (pageImage) {
      if (!this.toolsActive) {
        this.jumpToPage(pageImage.page, true)
        return
      }
      await this.closeEditor()
      this.currentPage = pageImage.page
      await this.$nextTick()
      this.openEditor()
    },
    openEditor () {
      if (!this.toolsActive || this.markerArea) {
        return
      }
      this.editingPage = this.currentPageImage
      this.editorTouched = false
      this.editorSaveStatus = ''
      this.mountEditor()
    },
    async closeEditor () {
      await this.saveEditor()
      this.unmountEditor()
    },
    // The size the browser actually shows the image at. For phone photos this already accounts for EXIF
    // rotation, which the server's recorded width/height don't, so markup always lines up with what's seen.
    naturalSize (img, pageImage) {
      const fallback = { width: pageImage.width || 1000, height: pageImage.height || 1300 }
      const read = () => (img.naturalWidth && img.naturalHeight
        ? { width: img.naturalWidth, height: img.naturalHeight }
        : fallback)
      if (img.complete && img.naturalWidth) {
        return Promise.resolve(read())
      }
      return new Promise(resolve => {
        img.addEventListener('load', () => resolve(read()), { once: true })
        img.addEventListener('error', () => resolve(fallback), { once: true })
      })
    },
    async mountEditor () {
      const container = this.$refs.editorContainer
      if (!container || !this.editingPage) {
        return
      }
      const img = document.createElement('img')
      img.src = this.editingPage.url
      const naturalSize = await this.naturalSize(img, this.editingPage)
      if (!this.editingPage) {
        return // closed while the image loaded
      }
      const saved = this.editorState || this.annotations[this.editingPage.page]
      this.editorRotation = saved ? +saved.rotation || 0 : 0
      const size = this.rotatedSize(naturalSize, this.editorRotation)
      const markerArea = new MarkerArea()
      markerArea.targetImage = img
      // edit at the image's natural size so saved coordinates don't depend on the screen
      markerArea.targetWidth = size.width
      markerArea.targetHeight = size.height
      this.imageSize = size
      markerArea.autoZoomOut = true
      markerArea.addEventListener('areastatechange', () => {
        this.updateUndoRedo()
        this.scheduleEditorSave()
      })
      markerArea.addEventListener('markerselect', () => this.updateUndoRedo())
      markerArea.addEventListener('markerdeselect', () => this.updateUndoRedo())
      // a box selection picks marks without one event per mark, so check once the drag ends
      markerArea.addEventListener('pointerup', () => setTimeout(() => this.updateUndoRedo(), 0))
      markerArea.addEventListener('markercreate', event => {
        const created = event.detail && event.detail.markerEditor
        if (created && this.markerType(created).endsWith('ImageMarker')) {
          this.centerStamp(created) // marker.js puts its corner at the click
        }
        if (this.tool !== 'TextMarker') {
          // arrows and drawing stay on, so several marks don't need several clicks
          setTimeout(() => this.armTool(), 0)
        }
        // a new text box opens with "Text" in a hidden text field; select it so the first keystroke replaces it
        setTimeout(() => {
          const field = markerArea.shadowRoot && markerArea.shadowRoot.activeElement
          if (field && field.tagName === 'TEXTAREA') {
            field.select()
          }
        }, 0)
      })
      // marker.js builds a new drawing layer each time its element is put into the page, but keeps its marks in
      // the old one, and partly fails while doing it. If the page around the editor moves it, start a fresh
      // editor with the same marks.
      let timesAttached = 0
      markerArea.addEventListener('areainit', () => {
        timesAttached++
        if (timesAttached > 1) {
          setTimeout(() => {
            if (this.markerArea === markerArea) {
              this.remountEditor()
            }
          }, 0)
        }
      })
      container.appendChild(markerArea)
      this.turnMarkerImage(markerArea, this.editorRotation, size.width, size.height)
      // marker.js fits the page each time it loads the image (on every page change and turn); keep the grader's zoom
      const editingImage = markerArea.shadowRoot && markerArea.shadowRoot.querySelector('img')
      if (editingImage) {
        editingImage.addEventListener('load', () => setTimeout(() => {
          if (this.markerArea === markerArea) {
            this.applyEditorZoom()
          }
        }, 0), { once: true })
      }
      if (saved) {
        // Undo goes back no further than the markup as it was loaded. Left alone, marker.js keeps a blank page
        // under it, so one Undo too many would wipe the saved markup.
        markerArea.addEventListener('arearestorestate', () => setTimeout(() => {
          const undoStack = markerArea.undoRedoManager && markerArea.undoRedoManager.undoStack
          if (Array.isArray(undoStack) && undoStack.length > 1) {
            undoStack.splice(0, undoStack.length - 1)
          }
          this.updateUndoRedo()
        }, 0), { once: true })
        markerArea.restoreState(saved, true)
      }
      this.markerArea = markerArea
      this.armTool()
      this.updateUndoRedo()
      // the pane can be hidden or resized while the tools are on
      if (window.ResizeObserver) {
        if (this.editorResizeObserver) {
          this.editorResizeObserver.disconnect()
        }
        // keeps the grader's zoom: fits the page again, then zooms back in by the same amount
        this.editorResizeObserver = new window.ResizeObserver(() => this.applyEditorZoom())
        this.editorResizeObserver.observe(container)
      } else {
        this.applyEditorZoom()
      }
    },
    unmountEditor () {
      clearTimeout(this.editorSaveTimer)
      if (this.editorResizeObserver) {
        this.editorResizeObserver.disconnect()
        this.editorResizeObserver = null
      }
      const container = this.$refs.editorContainer
      if (container) {
        while (container.firstChild) {
          container.removeChild(container.firstChild)
        }
      }
      this.markerArea = null
      this.editingPage = null
      this.editorState = null
    },
    async rotateEditor (degrees) {
      if (!this.markerArea) {
        return
      }
      this.markerArea.switchToSelectMode() // commits any text still being typed
      this.removeEmptyNotes()
      const state = this.markerArea.getState()
      state.rotation = this.editorRotation
      this.editorState = this.rotateState(state, degrees)
      clearTimeout(this.editorSaveTimer)
      const container = this.$refs.editorContainer
      while (container.firstChild) {
        container.removeChild(container.firstChild)
      }
      this.markerArea = null
      await this.mountEditor()
      this.editorTouched = true
      this.scheduleEditorSave(0)
    },
    updateUndoRedo () {
      this.canUndo = Boolean(this.markerArea && this.markerArea.isUndoPossible)
      this.canRedo = Boolean(this.markerArea && this.markerArea.isRedoPossible)
      this.editorHasMarks = Boolean(this.markerArea && this.markerArea.editors.length)
      this.selectedCount = this.markerArea && Array.isArray(this.markerArea.selectedMarkerEditors)
        ? this.markerArea.selectedMarkerEditors.length
        : 0
    },
    hideTooltips () {
      this.$root.$emit('bv::hide::tooltip')
    },
    // marker.js has no Delete key of its own. Delete or Backspace removes the selected marks, unless text is
    // being typed (then the key edits the text).
    editorKeyDown (event) {
      this.editorTouched = true
      if (!['Delete', 'Backspace'].includes(event.key) || !this.markerArea) {
        return
      }
      const target = event.composedPath ? event.composedPath()[0] : event.target
      const typing = target && (['TEXTAREA', 'INPUT'].includes(target.tagName) || target.isContentEditable)
      const selected = this.markerArea.selectedMarkerEditors || []
      if (typing || !selected.length || selected.some(editor => ['edit', 'creating'].includes(editor.state))) {
        return
      }
      event.preventDefault()
      this.deleteSelected()
    },
    loadMarkupSettings () {
      const settings = JSON.parse(window.localStorage.getItem('submissionPagesMarkupSettings') || '{}') || {}
      if (TOOLS.includes(settings.tool)) {
        this.tool = settings.tool
      }
      if (this.stamps.some(stamp => stamp.id === settings.stamp)) {
        this.stampId = settings.stamp
      }
      if (this.colors.some(item => item.value === settings.color)) {
        this.color = settings.color
      }
      if (this.sizes.some(item => item.value === settings.size)) {
        this.size = settings.size
      }
    },
    // remembered in this browser until the grader changes them
    saveMarkupSettings () {
      try {
        window.localStorage.setItem('submissionPagesMarkupSettings',
          JSON.stringify({ tool: this.tool, color: this.color, size: this.size, stamp: this.stampId }))
      } catch (error) {
      }
    },
    useTool (tool) {
      if (!this.markerArea) {
        return
      }
      this.tool = tool
      this.saveMarkupSettings()
      if (tool === 'select') {
        this.markerArea.switchToSelectMode()
        return
      }
      this.armTool()
    },
    useStamp (stampId) {
      this.stampId = stampId
      this.useTool('stamp')
      if (this.$refs.stampMenu) {
        this.$refs.stampMenu.hide(true) // back to the toggle, so the keyboard isn't lost
      }
    },
    stampStyle (stamp) {
      return stamp.inked ? { color: this.color, fontWeight: 'bold' } : {}
    },
    // Runs before marker.js sees the click. A click on a mark that's already there selects it, whatever tool is on,
    // so it can be moved, resized or deleted (otherwise a stamp or arrow tool would just add a new one on top).
    // A click on empty page makes a new mark with the tool, unless something is selected: then that click just
    // deselects it (and finishes a note being typed).
    editorPointerDown (event) {
      this.editorTouched = true
      const markerArea = this.markerArea
      if (!markerArea || this.tool === 'select') {
        return
      }
      const target = event.composedPath ? event.composedPath()[0] : event.target
      const onMark = markerArea.editors.some(editor => editor.state !== 'new' && editor.state !== 'creating' &&
        typeof editor.ownsTarget === 'function' && editor.ownsTarget(target))
      if (onMark) {
        markerArea.switchToSelectMode()
        return
      }
      const armed = markerArea.currentMarkerEditor && markerArea.currentMarkerEditor.state === 'new'
      if (!markerArea.selectedMarkerEditors.length && !armed) {
        this.armTool()
      }
    },
    // gets the chosen tool ready for the next mark, with the chosen color and size
    armTool () {
      if (!this.markerArea || this.tool === 'select') {
        return
      }
      if (this.tool === 'stamp') {
        this.armStamp()
        return
      }
      const editor = this.markerArea.createMarker(this.tool)
      if (editor) {
        this.applyColor(editor, this.color)
        this.applySize(editor, this.size)
      }
    },
    armStamp () {
      const stamp = this.currentStamp
      const editor = this.markerArea.createMarker(stamp.marker)
      if (!editor) {
        return
      }
      if (!stamp.inked) {
        // Drawn as text in an SVG, so it looks like the emoji on the grader's (and later the student's) device.
        // Its own fill: marker.js gives the stamp a transparent fill, which would hide the text.
        editor.marker.svgString = '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24">' +
          `<text x="12" y="19.5" font-size="19" text-anchor="middle" fill="#000" stroke="none">${stamp.glyph}</text></svg>`
      }
      this.applyColor(editor, this.color)
      this.applySize(editor, this.size)
    },
    centerStamp (editor) {
      const marker = editor.marker
      this.placeStamp(editor, marker.left, marker.top, marker.width)
    },
    // puts a stamp of the given size with its center at (x, y)
    placeStamp (editor, x, y, side) {
      const marker = editor.marker
      if (typeof marker.setSize !== 'function') {
        return
      }
      marker.left = x - side / 2
      marker.top = y - side / 2
      marker.width = side
      marker.height = side
      marker.setSize()
      if (typeof editor.adjustControlBox === 'function') {
        editor.adjustControlBox()
      }
    },
    markerType (editor) {
      return editor.marker && editor.marker.constructor ? editor.marker.constructor.typeName : ''
    },
    // Sizes are in the image's own pixels, so they scale with the image: about 2% of its longer side for text.
    // A phone photo can be 3000+ pixels wide, where marker.js's defaults (16px text, 3px lines) are unreadable.
    applySize (editor, size) {
      const option = this.sizes.find(item => item.value === size) || this.sizes[1]
      const longerSide = Math.max(this.imageSize.width, this.imageSize.height)
      if (this.markerType(editor).endsWith('ImageMarker')) {
        // a stamp about 5% of the page's longer side; one already placed keeps its spot and grows from its center
        const side = Math.max(24, Math.round(longerSide * 0.05 * option.factor))
        if (editor.state === 'new') {
          editor.marker.defaultSize = { width: side, height: side }
        } else {
          this.placeStamp(editor, editor.marker.left + editor.marker.width / 2,
            editor.marker.top + editor.marker.height / 2, side)
        }
        return
      }
      if ('fontSize' in editor) {
        editor.fontSize = { value: Math.max(12, Math.round(longerSide * 0.02 * option.factor)), units: 'px', step: 1 }
      } else if ('strokeWidth' in editor) {
        editor.strokeWidth = Math.max(2, Math.round(longerSide * 0.0025 * option.factor))
      }
    },
    useSize (size) {
      this.size = size
      this.saveMarkupSettings()
      this.armTool()
      if (this.markerArea) {
        this.markerArea.selectedMarkerEditors.forEach(editor => this.applySize(editor, size))
        this.markEditorChanged()
      }
    },
    // Text editors have a text color; arrows, freehand and the check and X stamps use the stroke color.
    // Emoji keep their own colors.
    applyColor (editor, color) {
      const type = this.markerType(editor)
      if (type === 'CustomImageMarker') {
        return
      }
      if ('color' in editor) {
        editor.color = color
      } else if ('strokeColor' in editor) {
        editor.strokeColor = color
      }
    },
    useColor (color) {
      this.color = color
      this.saveMarkupSettings()
      this.armTool()
      if (this.markerArea) {
        this.markerArea.selectedMarkerEditors.forEach(editor => this.applyColor(editor, color))
        this.markEditorChanged()
      }
    },
    undo () {
      this.markerArea && this.markerArea.undo()
      this.updateUndoRedo()
      this.markEditorChanged()
    },
    redo () {
      this.markerArea && this.markerArea.redo()
      this.updateUndoRedo()
      this.markEditorChanged()
    },
    deleteSelected () {
      if (!this.markerArea) {
        return
      }
      try {
        this.pruneDetachedMarks()
        this.markerArea.deleteSelectedMarkers()
      } catch (error) {
        // marker.js lost track of where it drew a mark; draw them all again so they can be selected
        this.remountEditor()
        this.$noty.error('That mark couldn\'t be deleted. Please select it again and then delete it.')
        return
      }
      this.updateUndoRedo()
      this.markEditorChanged()
    },
    // Replaces the editor with a fresh one holding the same marks and turn (Undo starts over from here).
    // Anything waiting to save still saves, from the new editor.
    async remountEditor () {
      const markerArea = this.markerArea
      if (!markerArea) {
        return
      }
      this.pruneDetachedMarks()
      const state = this.cleanState(markerArea.getState())
      if (this.editorRotation) {
        state.rotation = this.editorRotation
      }
      this.editorState = state
      if (markerArea.parentNode) {
        markerArea.parentNode.removeChild(markerArea)
      }
      this.markerArea = null
      await this.mountEditor()
    },
    // Start again: removes the marks on this page in one step that Undo can bring back. All pages: the other pages
    // are cleared on the server right away, and that can't be undone. Turned pages stay turned.
    async clearMarkup (allPages) {
      if (!this.markerArea) {
        return
      }
      const confirmed = await this.$bvModal.msgBoxConfirm(allPages
        ? 'This removes the markup on every page of this submission. Only this page\'s can be brought back with Undo. Pages you turned stay turned.'
        : 'This removes all the markup on this page. You can bring it back with Undo. A page you turned stays turned.', {
        title: allPages ? 'Clear all pages?' : 'Clear this page?',
        okTitle: 'Clear',
        okVariant: 'danger',
        cancelTitle: 'Cancel',
        buttonSize: 'sm',
        centered: true
      })
      if (!confirmed || !this.markerArea) {
        return
      }
      this.markerArea.switchToSelectMode()
      const state = this.markerArea.getState()
      if (state.markers.length) {
        this.markerArea.restoreState({ ...state, markers: [] }, true)
        this.updateUndoRedo()
        this.markEditorChanged()
      }
      if (!allPages) {
        return
      }
      await this.saveEditor()
      const editingPage = this.editingPage && this.editingPage.page
      for (const pageImage of this.pages) {
        if (pageImage.page !== editingPage && this.hasMarkup(pageImage.page)) {
          this.editorSaveStatus = 'saving'
          await this.sendEditorState(pageImage.page, { ...this.annotations[pageImage.page], markers: [] })
        }
      }
    },
    markEditorChanged () {
      this.editorTouched = true
      this.scheduleEditorSave()
    },
    // markup saves itself after a short pause; loading a page doesn't count as a change
    scheduleEditorSave (delay = 800) {
      if (!this.editorTouched) {
        return
      }
      clearTimeout(this.editorSaveTimer)
      this.editorSaveTimer = setTimeout(() => this.autosaveEditor(), delay)
    },
    // A text box that's open for typing. marker.js still calls a just-placed text box "selected", not "edit",
    // so the open typing box (which has the keyboard) is what tells.
    isTypingInEditor () {
      const markerArea = this.markerArea
      if (!markerArea) {
        return false
      }
      const active = markerArea.shadowRoot && markerArea.shadowRoot.activeElement
      if (active && (['TEXTAREA', 'INPUT'].includes(active.tagName) || active.isContentEditable)) {
        return true
      }
      return (markerArea.selectedMarkerEditors || [])
        .some(editor => editor.state === 'edit' || editor.state === 'creating')
    },
    autosaveEditor () {
      // a text box being typed in saves when the grader clicks away from it
      if (this.isTypingInEditor()) {
        this.scheduleEditorSave()
        return
      }
      return this.saveEditor(false)
    },
    async saveEditor (commitText = true) {
      clearTimeout(this.editorSaveTimer)
      if (this.editorSavePromise) {
        await this.editorSavePromise
      }
      if (!this.markerArea || !this.editingPage || !this.editorTouched) {
        return
      }
      if (commitText) {
        this.markerArea.switchToSelectMode() // commits any text still being typed
        clearTimeout(this.editorSaveTimer)
      }
      const page = this.editingPage.page
      this.removeEmptyNotes()
      const state = this.markerArea.getState()
      if (this.editorRotation) {
        state.rotation = this.editorRotation
      }
      if (JSON.stringify(state) === this.lastSavedSignature(page)) {
        return
      }
      this.editorSaveStatus = 'saving'
      this.editorSavePromise = this.sendEditorState(page, state)
      await this.editorSavePromise
      this.editorSavePromise = null
    },
    lastSavedSignature (page) {
      const saved = this.annotations[page]
      return saved ? JSON.stringify(saved) : JSON.stringify({ empty: page })
    },
    async sendEditorState (page, state) {
      try {
        const { data } = await axios.put(`${this.annotationsUrl}/page/${page}`, { state })
        if (data.type === 'error') {
          this.editorSaveStatus = 'failed'
          this.$noty.error(data.message)
          return
        }
        this.$set(this.annotations, page, state.markers.length || state.rotation ? state : null)
        this.editorSaveStatus = 'saved'
      } catch (error) {
        this.editorSaveStatus = 'failed'
        this.$noty.error(error.message)
      }
    }
  }
}
</script>

<style>
.submission-pages-toolbar {
  gap: 4px 0;
  padding: 6px 8px;
  margin-bottom: 6px;
  border: 1px solid #dee2e6;
  border-radius: 4px;
  background: #f8f9fa;
}

.page-chip {
  position: relative;
  min-width: 30px;
  height: 30px;
  margin: 2px 4px 2px 0;
  padding: 0 6px;
  border: 1px solid #ced4da;
  border-radius: 4px;
  background: #fff;
  font-size: 0.875rem;
  cursor: pointer;
}

.page-chip.active {
  border-color: #1f5fd1;
  background: #1f5fd1;
  color: #fff;
}

.page-chip-dot {
  position: absolute;
  top: 3px;
  right: 3px;
  width: 6px;
  height: 6px;
  border-radius: 50%;
  background: #d0021b;
}

.page-chip.active .page-chip-dot {
  background: #fff;
}

/* the pages scroll inside the viewer, so the toolbar stays put */
.submission-pages-scroll {
  position: relative;
  height: calc(100vh - 240px);
  min-height: 360px;
  overflow: auto; /* zoomed-in pages scroll sideways too */
  padding-right: 4px;
}

.zoom-level {
  min-width: 52px;
}

.stamp-glyph {
  display: inline-block;
  min-width: 1.2em;
  text-align: center;
  line-height: 1;
}

.stamp-dropdown .dropdown-menu {
  padding: 6px;
}

.stamp-grid {
  display: grid;
  grid-template-columns: repeat(7, 34px);
  gap: 4px;
}

.stamp-choice {
  width: 34px;
  height: 34px;
  padding: 0;
  border: 1px solid transparent;
  border-radius: 4px;
  background: transparent;
  font-size: 1.25rem;
  cursor: pointer;
}

.stamp-choice:hover,
.stamp-choice:focus {
  background: #f1f3f5;
  outline: none;
  border-color: #ced4da;
}

.stamp-choice.active {
  border-color: #1f5fd1;
  background: #e7efff;
}

.submission-page {
  margin-bottom: 16px;
}

.submission-page-label {
  height: 22px;
  text-align: center;
}

.submission-page-view.zoom-in {
  cursor: zoom-in;
}

.submission-page-view.zoom-out {
  cursor: zoom-out;
}

.submission-page-view .submission-page-image,
.submission-page-view mjs-marker-view {
  display: block;
  margin: 0 auto;
  border: 1px solid #dee2e6;
}

.submission-page-tools {
  margin-top: 4px;
}

.editor-save-status {
  min-height: 1.5em;
}

.submission-page-editor {
  height: calc(100vh - 330px);
  min-height: 360px;
  border: 1px solid #dee2e6;
  background: #f8f9fa;
}

.submission-page-editor mjs-marker-area {
  display: block;
  width: 100%;
  height: 100%;
}

.color-swatch {
  width: 24px;
  height: 24px;
  margin-right: 6px;
  border-radius: 50%;
  border: 2px solid #fff;
  box-shadow: 0 0 0 1px #adb5bd;
  cursor: pointer;
}

.color-swatch.active {
  box-shadow: 0 0 0 2px #212529;
}
</style>
