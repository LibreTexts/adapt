<template>
  <div class="learning-tree-editor">
    <AllFormErrors :all-form-errors="allFormErrors" :modal-id="'modal-form-errors-learning-tree'"/>
    <LearningTreeProperties :learning-tree-form="learningTreeForm"
                            :learning-tree-id="learningTreeId"
                            @resetLearningTreePropertiesModal="resetLearningTreePropertiesModal"
                            @saveLearningTreeProperties="saveLearningTreeProperties"
    />
    <b-modal v-if="questionToEdit && questionToEdit.id"
             :id="`modal-edit-question-${questionToEdit.id}`"
             size="xl"
             hide-footer
             dialog-class="modal-90"
             @hidden="reloadCurrentNode"
    >
      <template #modal-header>
        <div>
          <h2 class="h5 modal-title">
            Edit Question "{{ questionToEdit.title }}"
          </h2>
          <div>
            <small>ADAPT ID: <span :id="`edit-question-adapt-id-${questionToEdit.id}`">{{ questionToEdit.id }}</span>
            </small>
            <a href=""
               aria-label="Copy ADAPT ID"
               @click.prevent="doCopy(`edit-question-adapt-id-${questionToEdit.id}`)"
            >
              <font-awesome-icon :icon="copyIcon" class="text-muted pl-1"/>
            </a>
          </div>
        </div>
        <button type="button" aria-label="Close" class="close"
                @click="$bvModal.hide(`modal-edit-question-${questionToEdit.id}`)"
        >
          ×
        </button>
      </template>
      <iframe
        :src="`/source/edit/${questionToEdit.id}`"
        v-resize="{ log: false }"
        style="width:100%; border:none; display:block"
        allowtransparency="true"
      />
    </b-modal>
    <b-modal id="modal-learning-node-submission-response"
             hide-footer
             @shown="!earnedReset ? hideLineUnderTitle('modal-learning-node-submission-response') : ''"
    >
      <template #modal-title>
        <div :class="modalTitleClass">
          {{ learningNodeModalTitle }}
        </div>
      </template>
      <div v-show="earnedReset">
        <div class="float-right">
          <b-button size="sm" variant="outline-primary" @click="closeLearningTreeModal">
            Retry Root Question
          </b-button>
        </div>
      </div>
    </b-modal>
    <b-modal
      id="modal-cannot-answer-until-complete-parents"
      title="Cannot View"
      hide-footer
    >
      <p>Before you are able to view this question, you must complete all of the questions above it.</p>
      <p>
        <span v-if="uncompletedNodes.length>1">The following questions are not yet completed:</span>
        <span v-if="uncompletedNodes.length === 1">The following question is not yet completed:</span>
      </p>
      <ul>
        <li v-for="(uncompletedNode, uncompletedNodeIndex) in uncompletedNodes"
            :key="`uncompleted-nodes-${uncompletedNodeIndex}`"
        >
          {{ uncompletedNode }}
        </li>
      </ul>
    </b-modal>
    <b-modal
      id="modal-learning-tree-instructions"
      ref="modal-learning-tree-instructions"
      title="Instructions"
      size="lg"
    >
      <p>
        You can add nodes using the
        <b-button size="sm" aria-label="New Node" variant="outline-secondary" class="inline-button">
          New Node
        </b-button>
        button to create an empty node which can then be populated with a newly created question.
      </p>
      <p>
        To create a node based on an existing question, you can specify its contents by using the single number ADAPT
        ID found in "My Questions". Alternatively, if you already have a question in one of your assignments, you can
        visit that assignment and go to the Questions tab under Assignment Information to find the ADAPT ID; this ID
        will be of the form {number}-{number}.
      </p>
      <p>
        Next, drag the new node from the staging area to the canvas below. Each of the non-root assessment nodes
        should then be given a Branch Description to help students decide which nodes to visit as they navigate the
        tree. Using command+click on any of the nodes will open that node's editor.
      </p>
      <p>
        To remove a node, just drag it to the left of the screen. And, if you make a mistake, you can always use the
        undo icon (
        <font-awesome-icon
          aria-label="Undo"
          scale="1.1"
          :icon="undoIcon"
        />
        ).
      </p>
      <p>
        Finally, nodes are color-coded to help differentiate between the different types based on their contents:
      </p>
      <b-container class="pb-4">
        <b-row>
          <b-col style="width:200px">
            <div class="blockelem empty-node-border text-center pb-3">
              Empty learning tree nodes
            </div>
          </b-col>
          <b-col style="width:200px">
            <div class="blockelem exposition-border text-center pb-3">
              Exposition nodes
            </div>
          </b-col>
          <b-col>
            <div class="blockelem question-border text-center pb-3">
              Question nodes
            </div>
          </b-col>
        </b-row>
      </b-container>
      <template #modal-footer="{ ok }">
        <b-button size="sm" variant="primary" @click="$bvModal.hide('modal-learning-tree-instructions')">
          OK
        </b-button>
      </template>
    </b-modal>
    <b-modal
      v-if="nodeQuestion.title"
      id="modal-assignment-question-node"
      ref="modal"
      size="xl"
      no-close-on-backdrop
      no-close-on-esc
      hide-footer
      @hidden="updateLearningNodeToCompleted"
      @shown="logVisitedLearningTreeNode"
    >
      <template #modal-title>
        {{ nodeQuestion.title }}
        <div v-show="nodeQuestion.node_description" class="text-muted" style="font-size:16px;">
          {{ nodeQuestion.node_description }}
        </div>
      </template>
      <div v-if="!showNodeModalContents">
        <div class="d-flex justify-content-center mb-3">
          <div class="text-center">
            <b-spinner variant="primary" label="Text Centered"/>
            <span style="font-size:30px" class="text-primary"> Loading Contents</span>
          </div>
        </div>
      </div>
      <ViewQuestionWithoutModal :key="`question-to-view-${questionToViewKey}`"
                                :question-to-view="nodeQuestion"
                                :show-submit="true"
                                @receiveMessage="receiveMessage"
      />
      <div v-show="completedNodeMessage" style="width:100%">
        <hr>
        <b-alert variant="success" show>
          You have successfully completed this question.
        </b-alert>
      </div>
      <div v-if="nodeQuestion.technology === 'text' && !nodeQuestion.completed && timeLeft">
        <hr>
        <countdown :time="timeLeft"
                   @end="giveCreditForCompletingLearningTreeNode"
        >
          <template v-slot="props">
            <span v-html="getTimeLeftMessage(props)"/>
          </template>
        </countdown>
      </div>
    </b-modal>
    <b-modal
      id="modal-update-node"
      ref="modal"
      size="xl"
      no-close-on-backdrop
      no-close-on-esc
      hide-footer
      :modal-class="nodeModalBorderClass"
    >
      <template #modal-header="{ close }">
        <div>
          <h5>{{ nodeModalTitle || 'Node' }}</h5>
          <small>
            ADAPT ID:
            <span :id="`node-question-id-${nodeForm.question_id}`">{{ nodeForm.question_id }}</span></small>
          <a href=""
             aria-label="Copy Node ADAPT ID"
             @click.prevent="doCopy(`node-question-id-${nodeForm.question_id}`)"
          >
            <font-awesome-icon :icon="copyIcon" class="text-muted"/>
          </a>
        </div>
        <div>
          <b-button size="sm" variant="outline-secondary" @click="close()">
            Exit Node
          </b-button>
        </div>
      </template>
      <div v-if="!showNodeModalContents">
        <div class="d-flex justify-content-center mb-3">
          <div class="text-center">
            <b-spinner variant="primary" label="Text Centered"/>
            <span style="font-size:30px" class="text-primary"> Loading Contents</span>
          </div>
        </div>
      </div>
      <div v-if="isAuthor && showNodeModalContents" class="flex d-inline-flex pb-4" style="width:100%">
        <label class="pr-2" style="width:150px">Node Title</label>
        <b-form-input
          v-model="nodeForm.title"
          size="sm"
          placeholder="Enter a node title or leave blank to use the question title"
          type="text"
        />
      </div>
      <div v-show="showNodeModalContents" style="border: 2px solid #343a40; border-radius: 4px; padding: 10px;">
        <b-form ref="form">
          <b-form-group>
            <div v-if="isAuthor" class="flex d-inline-flex" style="width:100%">
              <label class="pr-2" style="width:120px">Source ID#
                <QuestionCircleTooltip :id="'source-id'"/>
              </label>
              <b-tooltip target="source-id" delay="500" triggers="hover focus">
                The ADAPT ID of the question used as the source for this node.
              </b-tooltip>
              <b-form-input
                id="node_question_id"
                v-model="nodeForm.question_id"
                type="text"
                size="sm"
                style="width: 100px"
                :class="{ 'is-invalid': nodeForm.errors.has('question_id') }"
                @keydown="nodeForm.errors.clear('question_id')"
              />
              <has-error :form="nodeForm" field="question_id"/>
              <span class="pl-2"><b-button size="sm" variant="info" @click="editSource">
                <b-icon icon="pencil"/> {{ questionToView.can_edit ? 'Edit' : 'View' }} Source
              </b-button></span>
            </div>
          </b-form-group>
          <div v-if="!isAuthor">
            <div>
              <label class="pr-2" style="width:120px">Source ID# {{ nodeForm.question_id }}
                <QuestionCircleTooltip :id="'source-id-non-author'"/>
              </label>
              <b-tooltip target="source-id-non-author" delay="500" triggers="hover focus">
                The ADAPT ID of the question used as the source for this node.
              </b-tooltip>
              <b-button size="sm" variant="info" @click="editSource">
                View Source
              </b-button>
            </div>
          </div>
        </b-form>
        <div v-if="isAuthor" class="flex d-inline-flex pb-4" style="width:100%">
          <label class="pr-2" style="width:137px">Source Title
            <QuestionCircleTooltip :id="'source-title'"/>
          </label>
          <b-tooltip target="source-title" delay="500" triggers="hover focus">
            The title of the question used as the source for this node. Automatically pulled from the source question
            and cannot be edited here.
          </b-tooltip>
          <b-form-input
            v-model="questionToView.title"
            size="sm"
            disabled
            placeholder="Enter a node title or leave blank to use the source title"
            type="text"
          />
        </div>
        <div :style="`border: 4px solid ${nodeModalBorderColor}; border-radius: 4px; padding: 10px;`">
          <ViewQuestionWithoutModal :key="`question-to-view-${questionToViewKey}`" :question-to-view="questionToView"/>
        </div>
      </div>
      <div v-if="showNodeModalContents">
        <hr>
        <div v-if="!isRootNode">
          <b-form-group
            v-if="isAuthor"
            label="Node Description*"
            label-for="node_description"
            class="mb-3"
          >
            <b-form-textarea
              id="node_description"
              v-model="nodeForm.node_description"
              type="text"
              :class="{ 'is-invalid': nodeForm.errors.has('node_description') }"
              rows="3"
              @keydown="nodeForm.errors.clear('node_description')"
            />
            <has-error :form="nodeForm" field="node_description"/>
          </b-form-group>
          <div v-if="!isAuthor">
            Node Description: {{ nodeForm.node_description ? nodeForm.node_description : 'None provided.' }}
          </div>
        </div>
        <div v-if="isAuthor">
          <b-form-group
            label="Private notes"
            label-for="notes"
            class="mb-3"
          >
            <b-form-textarea
              id="notes"
              v-model="nodeForm.notes"
              type="text"
              rows="3"
            />
          </b-form-group>
        </div>
        <div class="float-right">
          <b-button size="sm" variant="outline-secondary" @click="$bvModal.hide('modal-update-node')">
            Exit Node
          </b-button>
          <span v-if="isAuthor">
            <b-button size="sm"
                      variant="primary"
                      :disabled="isUpdating"
                      @click="submitUpdateNode"
            >
              <span v-if="!isUpdating">Save</span>
              <span v-if="isUpdating"><b-spinner small type="grow"/> Updating...</span>
            </b-button>
          </span>
        </div>
      </div>
    </b-modal>
    <!-- TOP TOOLBAR -->
    <div v-if="isAuthor" id="toolbar">
      <b-icon id="properties-tooltip"
              icon="gear"
              :class="{ 'disabled': learningTreeId === 0}"
              :aria-disabled="learningTreeId === 0"
              scale="1.1"
              class="toolbar-icon"
              @click="learningTreeId === 0 ? '' : editLearningTree()"
      />
      <b-tooltip target="properties-tooltip" delay="250" triggers="hover">
        Edit properties
      </b-tooltip>

      <font-awesome-icon id="undo-action-tooltip"
                         :class="{ 'disabled': !canUndo}"
                         aria-label="Undo"
                         class="toolbar-icon"
                         scale="1.1"
                         :icon="undoIcon"
                         @click="!canUndo ? '' : undo()"
      />
      <b-tooltip target="undo-action-tooltip" delay="250" triggers="hover">
        Undo the last action
      </b-tooltip>

      <b-button :class="{ 'disabled': learningTreeId === 0 || nodeIsPending }"
                :aria-disabled="learningTreeId === 0 || nodeIsPending"
                :disabled="learningTreeId === 0 || nodeIsPending"
                variant="outline-secondary"
                size="sm"
                class="toolbar-btn"
                @click="addRemediation"
      >
        <b-spinner v-if="validatingQuestionId" small label="Spinning"/>
        New Node
      </b-button>

      <b-button size="sm"
                variant="outline-info"
                class="toolbar-btn"
                @click="$bvModal.show('modal-learning-tree-instructions')"
      >
        Instructions
      </b-button>
      <b-button size="sm"
                variant="outline-secondary"
                class="toolbar-btn"
                @click="updateLocation()"
      >
        Recenter
      </b-button>
    </div>

    <!-- STAGING AREA: shown when a new node is pending placement -->
    <div v-if="isAuthor && nodeIsPending" id="staging-area">
      <span class="staging-hint">↓ Drag this node onto the canvas below</span>
      <div id="blocklist"/>
    </div>

    <!-- CANVAS -->
    <div id="canvas">
      <div id="canvas-inner"/>
    </div>
  </div>
</template>

<script>

import { flowy } from '~/helpers/Flowy'
import { LEARNING_TREE_TEMPLATE } from '~/helpers/LearningTreeTemplate'
import $ from 'jquery'
import axios from 'axios'
import Form from 'vform'
import { mapGetters } from 'vuex'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faUndo } from '@fortawesome/free-solid-svg-icons'
import { faCopy } from '@fortawesome/free-regular-svg-icons'
import AllFormErrors from '~/components/AllFormErrors'
import ViewQuestionWithoutModal from '~/components/ViewQuestionWithoutModal'
import { h5pResizer } from '~/helpers/H5PResizer'
import 'vue-select/dist/vue-select.css'
import { getLearningOutcomes, subjectOptions } from '~/helpers/LearningOutcomes'
import { processReceiveMessage, addGlow, getTechnology, getTechnologySrcDoc } from '~/helpers/HandleTechnologyResponse'
import LearningTreeProperties from '../../components/LearningTreeProperties.vue'
import { doCopy } from '../../helpers/Copy'

window.onmousemove = function (e) {
  window.doNotDrag = e.ctrlKey || e.metaKey
}

export default {
  metaInfo () {
    return { title: 'Learning Trees Editor' }
  },
  components: {
    LearningTreeProperties,
    FontAwesomeIcon,
    AllFormErrors,
    ViewQuestionWithoutModal
  },
  data: () => ({
    questionToEdit: {},
    nodeModalTitle: '',
    nodeModalBorderClass: '',
    copyIcon: faCopy,
    xCenter: '0',
    earnedReset: false,
    questionNodeTitle: '',
    modalTitleClass: '',
    learningNodeModalTitle: '',
    event: {},
    submitButtonActive: true,
    submitButtonsDisabled: false,
    submissionArray: [],
    uncompletedNodes: [],
    learningTreeNodeUncompletedParentNodeTitlesByQuestionId: [],
    completedNodeMessage: false,
    timeLeft: 0,
    nodeQuestion: {},
    rootNodeQuestionId: 0,
    questionId: '',
    isAuthor: false,
    fromAllLearningTrees: 0,
    learningOutcome: '',
    subject: null,
    subjectOptions: subjectOptions,
    learningOutcomeOptions: [],
    isUpdating: false,
    isRootNode: false,
    questionToViewKey: 0,
    showNodeModalContents: false,
    questionToView: {},
    allFormErrors: [],
    nodeSrc: '',
    nodeIframeId: '',
    canUndo: false,
    undoIcon: faUndo,
    nodeIsPending: false,
    nodeForm: new Form({
      question_id: '',
      title: '',
      notes: '',
      node_description: '',
      learning_outcome_description: ''
    }),
    nodeToUpdate: {},
    learningTreeForm: new Form({
      title: '',
      description: '',
      notes: '',
      public: 0,
      question_id: ''
    }),
    assessmentQuestionId: '',
    touchingBlock: false,
    validatingQuestionId: false,
    panelHidden: false,
    studentLearningObjectives: '',
    title: window.config.appName,
    chosenId: '',
    learningTreeId: 0
  }),
  computed: {
    ...mapGetters({
      user: 'auth/user'
    }),
    nodeModalBorderColor () {
      switch (this.nodeModalBorderClass) {
        case 'modal-border-blue':
          return 'cornflowerblue'
        case 'modal-border-red':
          return 'rosybrown'
        default:
          return 'darkgray'
      }
    }
  },
  created () {
    h5pResizer()
    this.getLearningOutcomes = getLearningOutcomes
    if (this.user.role !== 3) {
      window.addEventListener('keydown', this.hotKeys)
    }
    window.addEventListener('message', this.receiveMessage, false)
  },
  destroyed () {
    if (this.user.role !== 3) {
      window.removeEventListener('keydown', this.hotKeys)
    }
  },
  async mounted () {
    if (this.user.role === 3) {
      this.assignmentId = this.$route.params.assignmentId
      this.rootNodeQuestionId = this.$route.params.rootNodeQuestionId
    }
    this.xCenter = this.$route.params.xCenter

    let tempblock
    let tempblock2
    let vm = this

    flowy(document.getElementById('canvas'), drag, release, snapping, rearranging, 20, 30)

    function addEventListenerMulti (type, listener, capture, selector) {
      let nodes = document.querySelectorAll(selector)
      for (let i = 0; i < nodes.length; i++) {
        nodes[i].addEventListener(type, listener, capture)
      }
    }

    function rearranging (block, parent) {
      // Needed so that I could redefine the y distance in flowy
    }

    function snapping (drag, first) {
      let grab = drag.querySelector('.grabme')
      grab.parentNode.removeChild(grab)

      let blockin = drag.querySelector('.blockin')
      blockin.parentNode.removeChild(blockin)

      let isAssessmentNode = (drag.querySelector('.blockelemtype').value === '1')
      let title = blockin.querySelector('.title').innerHTML
      let blockynameContents = blockin.querySelector('.blockyname').innerHTML
      let body = isAssessmentNode ? 'The original question'
        : `${blockynameContents}<span class="extra"></span></div>`

      drag.innerHTML += `
        <span class="blockyname" style="margin-bottom:0;">${body}</span>
        <div class="blockyinfo">${title}</div>`

      vm.nodeIsPending = false
      return true
    }

    function drag (block) {
      block.classList.add('blockdisabled')
      tempblock2 = block
    }

    function release () {
      if (tempblock2) {
        tempblock2.classList.remove('blockdisabled')
      }
      vm.nodeIsPending = false
    }

    let aclick = false
    let noinfo = false
    let isSaving = false
    let mouseDownX = 0
    let mouseDownY = 0

    let beginTouch = function (event) {
      aclick = true
      noinfo = false
      mouseDownX = event.clientX
      mouseDownY = event.clientY
      console.error('beginTouch scrollLeft:', document.getElementById('canvas').scrollLeft)
      vm.touchingBlock = event.target.closest('#canvas') || event.target.closest('#blocklist')
      if (event.target.closest('.create-flowy')) {
        noinfo = true
      }
    }

    let checkTouch = function (event) {
      const totalMovement = Math.abs(event.clientX - mouseDownX) + Math.abs(event.clientY - mouseDownY)
      if (totalMovement > 8) {
        aclick = false
      }
    }

    let doneTouch = function (event) {
      document.querySelectorAll('.block.dragging').forEach(el => el.classList.remove('dragging'))
      if (vm.touchingBlock && !aclick) {
       vm.updateLocation()
        isSaving = true
        document.getElementById('canvas').style.cursor = 'wait'
        vm.saveLearningTree().finally(() => {
          isSaving = false
          document.getElementById('canvas').style.cursor = 'default'
        })
      }

      if (event.type === 'mouseup' && aclick && !noinfo && !isSaving) {
        alert('b')
        if (event.target.closest('.block') && !event.target.closest('.block').classList.contains('dragging')) {
          vm.openNodeModal(event.target.closest('.block'))
          tempblock = event.target.closest('.block')
          if (document.getElementById('properties')) {
            document.getElementById('properties').classList.add('expanded')
          }
          tempblock.classList.add('selectedblock')
        }
      }
    }

    let openNodeModal = function (event) {
      vm.openNodeModal(event.target.closest('.block'))
    }

    addEventListener('dblclick', openNodeModal, false)
    addEventListener('mousedown', beginTouch, false)
    addEventListener('mousemove', checkTouch, false)
    addEventListener('mouseup', doneTouch, false)
    addEventListenerMulti('touchstart', beginTouch, false, '.block')

    this.learningTreeId = parseInt(this.$route.params.learningTreeId)
    this.fromAllLearningTrees = this.$route.params.fromAllLearningTrees

    if (this.learningTreeId === 0) {
      this.isAuthor = true
      this.$bvModal.show('modal-learning-tree-properties')
    } else {
      await this.getLearningTreeLearningTreeId(this.learningTreeId)
      await this.$nextTick()
      this.updateCanvasHeight()
      await this.updateLocation()
      if (this.user.role === 3) {
        await this.updateCompletionBorders()
      } else {
        let questionIds = this.getQuestionIdsFromNodes()
        let questionTypes = await this.getQuestionTypes(questionIds)
        this.updateBorders(questionTypes)
      }
    }
  },
  beforeRouteLeave (to, from, next) {
    if (to.name === 'instructors.learning_trees.index') {
      next(false)
      location.replace('/instructors/learning-trees')
    } else {
      next()
    }
  },
  beforeDestroy () {
    window.removeEventListener('message', this.receiveMessage)
  },
  methods: {
    doCopy,
    getTechnologySrcDoc,
    addGlow,
    processReceiveMessage,
    getTechnology,
    syncBlockPositions () {
      const canvas = document.getElementById('canvas')
      const blocks = flowy.getBlocks && flowy.getBlocks()
      if (!canvas || !blocks || !blocks.length) return
      blocks.forEach(block => {
        const blockEl = canvas.querySelector('.blockid[value="' + block.id + '"]')
        if (!blockEl) return
        const elRect = blockEl.parentNode.getBoundingClientRect()
        block.x = elRect.left + window.scrollX + (block.width / 2) + canvas.scrollLeft
        block.y = elRect.top + window.scrollY + (block.height / 2) + canvas.scrollTop
      })
    },
    updateCanvasHeight () {
      this.$nextTick(() => {
        let maxBottom = 0
        let maxRight = 0
        $('#canvas .block').each(function () {
          const bottom = (parseFloat($(this).css('top')) || 0) + ($(this).outerHeight() || 0)
          const right = (parseFloat($(this).css('left')) || 0) + ($(this).outerWidth() || 0)
          if (bottom > maxBottom) maxBottom = bottom
          if (right > maxRight) maxRight = right
        })
        const inner = document.getElementById('canvas-inner')
        if (inner && maxBottom > 0) {
          inner.style.height = maxBottom + 200 + 'px'
          inner.style.width = maxRight + 200 + 'px'
        }
      })
    },
    listenForIframeClose () {
      window.addEventListener('message', (event) => {
        if (event.data === 'question-saved' || event.data === 'question-cancelled') {
          this.$bvModal.hide(`modal-edit-question-${this.questionToEdit.id}`)
        }
      })
    },
    async reloadCurrentNode () {
      if (this.nodeForm.question_id) {
        await this.getQuestionToView(this.nodeForm.question_id)
      }
    },
    setNodeModalTitleAndBorderClass () {
      if (this.user.role !== 2) {
        return
      }
      const blockElem = this.nodeToUpdate.querySelector('.blockelem') || this.nodeToUpdate
      if (this.isRootNode) {
        this.nodeModalTitle = 'Root Assessment Node'
        this.nodeModalBorderClass = 'modal-border-blue'
      } else if (blockElem.classList.contains('question-border')) {
        this.nodeModalTitle = 'Assessment Node'
        this.nodeModalBorderClass = 'modal-border-blue'
      } else if (blockElem.classList.contains('exposition-border')) {
        this.nodeModalTitle = 'Exposition Node'
        this.nodeModalBorderClass = 'modal-border-red'
      } else {
        this.nodeModalTitle = 'Empty Node'
        this.nodeModalBorderClass = 'modal-border-gray'
      }
    },
    async updateLocation () {
      console.error('canvas getBoundingClientRect:', document.getElementById('canvas').getBoundingClientRect())
      console.error('window.scrollY:', window.scrollY)
      const canvas = document.getElementById('canvas')
      const blockElems = $('.blockelem')
      if (!blockElems.length) return

      let minTop = Infinity
      $('.blockelem, .arrowblock').each(function () {
        const top = parseFloat($(this).css('top')) || 0
        if (top < minTop) minTop = top
      })

      const rootBlockId = canvas.querySelector('.blockid[value="0"]')
      const rootBlock = rootBlockId ? rootBlockId.parentNode : blockElems.first()[0]
      const rootLeft = parseFloat($(rootBlock).css('left')) || 0
      const rootWidth = rootBlock.offsetWidth || 242
      const canvasWidth = canvas.offsetWidth
      const rootCenter = rootLeft + (rootWidth / 2)
      const xShift = (canvasWidth / 2) - rootCenter
      const yShift = 20 - minTop

      // Shift all DOM elements
      $('.blockelem, .arrowblock').each(function () {
        const currentLeft = parseFloat($(this).css('left')) || 0
        const currentTop = parseFloat($(this).css('top')) || 0
        $(this).css('left', `${currentLeft + xShift}px`)
        $(this).css('top', `${currentTop + yShift}px`)
      })

      // Resync blocks array from actual DOM positions after shifting
      const blocks = flowy.getBlocks()
      if (blocks && blocks.length) {
        const canvasRect = canvas.getBoundingClientRect()
        blocks.forEach(block => {
          const blockEl = canvas.querySelector('.blockid[value="' + block.id + '"]')
          if (blockEl) {
            const el = blockEl.parentNode
            const elRect = el.getBoundingClientRect()
            block.x = elRect.left + window.scrollX + (block.width / 2) + canvas.scrollLeft
            block.y = elRect.top + window.scrollY + (block.height / 2) + canvas.scrollTop
          }
        })
      }
      flowy.rearrange()

      // Set canvas height to contain all content plus padding
      await this.$nextTick()
      this.updateCanvasHeight()
      flowy.rearrange()
    },
    async logVisitedLearningTreeNode () {
      try {
        const { data } = await axios.post(`/api/learning-tree-node-assignment-question/assignment/${this.assignmentId}/learning-tree/${this.learningTreeId}/question/${this.nodeQuestion.id}/log-visit`)
        if (data.type === 'error') {
          console.error(`Error logging visit to learning tree node: ${data.message}`)
        }
      } catch (error) {
        this.$noty.error(error.message)
      }
    },
    closeLearningTreeModal () {
      this.$bvModal.hide('modal-learning-node-submission-response')
      window.parent.postMessage('Close learning tree modal', '*')
    },
    hideLineUnderTitle (modalId) {
      $('#' + modalId + '___BV_modal_body_').hide()
    },
    async showResponse (response) {
      try {
        const { data } = await axios.get(`/api/learning-tree-node-submission/${response.learning_tree_node_submission_id}`)
        if (data.type === 'error') {
          this.$noty.error(data.message)
          return false
        }
        if (data.show_submission_message) {
          this.learningNodeModalTitle = data.message
          this.modalTitleClass = data.correct_submission ? 'text-success' : 'text-danger'
          this.earnedReset = data.earned_reset
          this.$bvModal.show('modal-learning-node-submission-response')
        }
        let info = ['last_submitted', 'student_response', 'submission_count', 'session_jwt',
          'qti_answer_json', 'qti_json', 'technology_iframe_src', 'submission_array']
        for (let i = 0; i < info.length; i++) {
          this.nodeQuestion[info[i]] = data[info[i]]
        }
        if (this.nodeQuestion['technology'] === 'webwork') {
          if (data.technology_iframe_src) {
            this.nodeQuestion.technology_iframe = data.technology_iframe_src
            let vm = this
            await this.getTechnologySrcDoc(vm, data.technology_iframe_src, this.assignmentId, this.nodeQuestion.id, 'learning_tree_node_submissions', this.learningTreeId)
            this.cacheIndex++
          }
        }
        if (this.nodeQuestion['technology'] === 'imathas') {
          this.nodeQuestion.technology_iframe = data.technology_iframe_src
        }
        if (['webwork', 'imathas'].includes(this.nodeQuestion['technology'])) {
          this.submissionArray = data['submission_array']
        }
        this.qtiJson = this.nodeQuestion['qti_json']
      } catch (error) {
        this.learningNodeModalTitle = error.message
        this.$bvModal.show('modal-learning-node-submission-response')
      }
    },
    receiveMessage (event) {
      if (event.data === 'question-saved' || event.data === 'question-cancelled') {
        this.$bvModal.hide(`modal-edit-question-${this.questionToEdit.id}`)
        return false
      }
      if (event.data === 'scroll-to-top') {
        const modalBody = document.querySelector(`#modal-edit-question-${this.questionToEdit.id} .modal-body`)
        if (modalBody) {
          modalBody.scrollTop = 0
        }
        return false
      }
      let vm = this
      this.processReceiveMessage(vm, this.$route.name, event)
    },
    updateLearningNodeToCompleted () {
      location.reload()
    },
    async giveCreditForCompletingLearningTreeNode () {
      try {
        const { data } = await axios.post(`/api/learning-tree-node-assignment-question/assignment/${this.assignmentId}/learning-tree/${this.learningTreeId}/question/${this.nodeQuestion.id}/give-credit-for-completion`)
        if (data.type === 'error') {
          this.$noty.error(data.message)
          return false
        }
        this.completedNodeMessage = true
        this.nodeQuestion.completed = true
      } catch (error) {
        this.$noty.error(error.message)
      }
    },
    async updateCompletionBorders () {
      let questionIds = this.getQuestionIdsFromNodes()
      let learningTreeNodeCompletions = await this.getLearningTreeNodeCompletions(questionIds)
      this.updateBorders(learningTreeNodeCompletions)
    },
    getTimeLeftMessage (props) {
      let message = '<span class="font-weight-bold">Time Left For Credit:</span> '
      let timeLeft = parseInt(this.timeLeft) / 1000
      let pluralSec = props.seconds > 1 ? 's' : ''
      if (timeLeft > 60) {
        let pluralMin = props.minutes > 1 ? 's' : ''
        message += `${props.minutes} minute${pluralMin}, ${props.seconds} second${pluralSec}`
      } else {
        message += `${props.seconds} second${pluralSec}`
      }
      if (this.user.fake_student) {
        message += ' (5 seconds for Student View)'
      }
      return message
    },
    hotKeys (event) {
      if (event.ctrlKey && event.key === 'S' && $('#modal-update-node').length) {
        this.submitUpdateNode()
      }
    },
    async getLearningTreeNodeCompletions () {
      try {
        const { data } = await axios.get(`/api/learning-tree-node-assignment-question/assignment/${this.assignmentId}/learning-tree/${this.learningTreeId}/completion-info`)
        if (data.type === 'error') {
          this.$noty.error(data.message)
          return false
        }
        this.learningTreeNodeUncompletedParentNodeTitlesByQuestionId = data.learning_tree_node_uncompleted_parent_node_titles_by_question_id
        return data.learning_tree_node_completion_info
      } catch (error) {
        this.$noty.error(error.message)
      }
    },
    async getQuestionTypes (questionIds) {
      try {
        const { data } = await axios.post('/api/questions/question-types', { question_ids: questionIds })
        if (data.type === 'error') {
          this.$noty.error(data.message)
          return false
        }
        return data.question_types
      } catch (error) {
        this.$noty.error(error.message)
      }
    },
    async validateAssignmentAndQuestionId (assignmentQuestionId, isRootNode) {
      if (assignmentQuestionId === '') {
        assignmentQuestionId = 0
      }
      try {
        const { data } = await axios.get(`/api/learning-trees/validate-remediation-by-assignment-question-id/${assignmentQuestionId}/${Number(isRootNode)}`)
        if (data.type === 'error') {
          this.$noty.error(data.message)
          return false
        }
        return data.question
      } catch (error) {
        this.$noty.error(error.message)
      }
      return false
    },
    async editSource () {
      const questionId = this.nodeForm.question_id.split('-').pop()
      try {
        const { data } = await axios.get(`/api/questions/${questionId}`)
        if (data.type !== 'success') {
          this.$noty.error(data.message)
          return false
        }
        this.questionToEdit = data.question
        await this.$nextTick()
        this.$bvModal.show(`modal-edit-question-${this.questionToEdit.id}`)
      } catch (error) {
        this.$noty.error(error.message)
      }
    },
    async undo () {
      try {
        const { data } = await axios.patch(`/api/learning-tree-histories/${this.learningTreeId}`)
        if (data.type === 'success') {
          window.location.href = `/instructors/learning-trees/editor/${this.learningTreeId}`
        } else {
          this.$noty[data.type](data.message)
        }
      } catch (error) {
        this.$noty.error(error.message)
      }
    },
    async openNodeModal (nodeToUpdate) {
      if (!nodeToUpdate) {
        return false
      }
      this.isUpdating = false
      this.nodeForm.errors.clear()
      this.questionToView = {}
      this.nodeToUpdate = nodeToUpdate.closest('.block')
      this.showNodeModalContents = false
      let questionId = this.nodeToUpdate.querySelector('input[name="question_id"]').value
      this.isRootNode = parseInt(this.nodeToUpdate.querySelector('input[name="blockid"]').value) === 0
      this.nodeForm.is_root_node = this.isRootNode
      this.setNodeModalTitleAndBorderClass()
      if (this.assignmentId) {
        this.uncompletedNodes = []
        if (this.learningTreeNodeUncompletedParentNodeTitlesByQuestionId[questionId].length) {
          this.uncompletedNodes = this.learningTreeNodeUncompletedParentNodeTitlesByQuestionId[questionId]
          this.$bvModal.show('modal-cannot-answer-until-complete-parents')
          return false
        }
        this.isRootNode ? this.closeLearningTreeModal()
          : this.$bvModal.show('modal-assignment-question-node')
        await this.getAssignmentNodeQuestionToView(questionId)
      } else {
        this.$bvModal.show('modal-update-node')
        this.nodeForm.original_question_id = questionId
        this.nodeForm.question_id = questionId
        await this.getQuestionToView(questionId)
        await this.getNodeMetaInformation(questionId)
        this.nodeIframeId = `remediation-${questionId}`
      }
      this.showNodeModalContents = true
    },
    async getAssignmentNodeQuestionToView (nodeQuestionId) {
      nodeQuestionId = nodeQuestionId.includes('-') ? nodeQuestionId.split('-').pop() : nodeQuestionId
      try {
        const { data } = await axios.get(`/api/learning-tree-node-assignment-question/assignment/${this.assignmentId}/learning-tree/${this.learningTreeId}/question/${nodeQuestionId}`)
        if (data.type !== 'success') {
          this.$noty.error(data.message)
          return false
        }
        this.questionToViewKey++
        this.nodeQuestion = data.node_question
        if (this.nodeQuestion.technology === 'text' || this.nodeQuestion.question_type === 'exposition') {
          this.timeLeft = this.nodeQuestion.time_left
        } else {
          if (this.nodeQuestion.learning_tree_node_submission_id) {
            await this.showResponse({ learning_tree_node_submission_id: this.nodeQuestion.learning_tree_node_submission_id })
          }
        }
        this.completedNodeMessage = false
      } catch (error) {
        this.$noty.error(error.message)
      }
    },
    async getQuestionToView (questionId, updateModalStyle = false) {
      questionId = questionId.split('-').pop()
      try {
        const { data } = await axios.get(`/api/questions/${questionId}`)
        if (data.type !== 'success') {
          this.$noty.error(data.message)
          return false
        }
        this.questionToView = data.question
        this.questionToViewKey++
        if (updateModalStyle) {
          const blockElem = this.nodeToUpdate.querySelector('.blockelem') || this.nodeToUpdate
          blockElem.classList.remove('question-border', 'exposition-border', 'empty-node-border')
          blockElem.classList.add(
            this.questionToView.question_type === 'assessment' ? 'question-border'
              : this.questionToView.question_type === 'exposition' ? 'exposition-border'
                : 'empty-node-border'
          )
          this.setNodeModalTitleAndBorderClass()
        }
      } catch (error) {
        if (error.message.includes('404')) {
          this.$noty.error(`There is no question with the ADAPT ID ${questionId}.`)
        } else {
          this.$noty.error(error.message)
        }
      }
    },
    async getNodeMetaInformation (questionId) {
      try {
        const { data } = await axios.get(`/api/learning-tree-node/meta-info/${this.learningTreeId}/${questionId}`)
        if (data.type !== 'success') {
          this.$noty.error(data.message)
          return false
        }
        this.nodeForm.node_description = data.description
        this.nodeForm.title = data.title
        this.nodeForm.notes = data.notes
        this.subject = data.subject
        if (data.learning_outcome) {
          if (data.subject && data.subject !== this.subject) {
            await this.getLearningOutcomes(data.subject)
          }
          this.learningOutcome = data.learning_outcome
        } else {
          await this.getLearningOutcomes(data.subject)
        }
        this.nodeForm.learning_outcome = data.learning_outcome
      } catch (error) {
        this.$noty.error(error.message)
      }
    },
    async submitUpdateNode () {
      this.isUpdating = true
      this.nodeForm.question_id = this.nodeForm.question_id.split('-').pop()
      this.nodeForm.learning_outcome = this.learningOutcome ? this.learningOutcome.id : ''
      try {
        const { data } = await this.nodeForm.patch(`/api/learning-trees/nodes/${this.learningTreeId}`)
        if (data.type === 'success') {
          this.nodeToUpdate.querySelector('input[name="question_id"]').value = this.nodeForm.question_id
          this.nodeToUpdate.querySelector('.blockyinfo').innerHTML = data.title
          this.nodeToUpdate.querySelector('.blockyname').innerHTML = this.getBlockyNameHTML(this.nodeForm.question_id)
          await this.saveLearningTree(this.nodeForm.question_id)
        } else {
          this.$noty.error(data.message, { timeout: 20000 })
        }
        this.$bvModal.hide('modal-update-node')
      } catch (error) {
        if (!error.message.includes('status code 422')) {
          this.$noty.error(error.message)
        } else {
          this.allFormErrors = this.nodeForm.errors.flatten()
          this.$bvModal.show('modal-form-errors-learning-tree')
        }
        this.isUpdating = false
      }
    },
    resetAll () {
      this.learningTreeId = 0
      document.getElementById('canvas').innerHTML = ''
      this.learningTreeForm.question_id = ''
    },
    editLearningTree () {
      this.learningTreeForm.title = this.title
      this.learningTreeForm.description = this.description
      this.learningTreeForm.public = this.public
      this.learningTreeForm.notes = this.notes
      this.$bvModal.show('modal-learning-tree-properties')
    },
    resetLearningTreePropertiesModal () {
      this.learningTreeForm.title = ''
      this.learningTreeForm.description = ''
      this.learningTreeForm.errors.clear()
    },
    resetLearningTreeModal (modalId) {
      this.resetLearningTreePropertiesModal()
      this.$nextTick(() => {
        this.$bvModal.hide(modalId)
      })
    },
    saveLearningTreeProperties () {
      !this.learningTreeId ? this.createLearningTree() : this.updateLearningTreeInfo()
    },
    async createLearningTree () {
      try {
        const { data } = await this.learningTreeForm.post('/api/learning-trees/info')
        this.$noty[data.type](data.message)
        if (data.type === 'success') {
          this.learningTreeId = data.learning_tree_id
          this.title = this.learningTreeForm.title
          this.description = this.learningTreeForm.description
          this.public = this.learningTreeForm.public
          this.notes = this.learningTreeForm.notes
          this.assessmentQuestionId = this.learningTreeForm.question_id
          this.$bvModal.hide('modal-learning-tree-properties')
          flowy.import(LEARNING_TREE_TEMPLATE)
          await this.saveLearningTree()
        }
      } catch (error) {
        if (!error.message.includes('status code 422')) {
          this.$noty.error(error.message)
        } else {
          this.allFormErrors = this.learningTreeForm.errors.flatten()
          this.$bvModal.show('modal-form-errors-learning-tree')
        }
      }
    },
    async updateLearningTreeInfo () {
      try {
        const { data } = await this.learningTreeForm.post(`/api/learning-trees/info/${this.learningTreeId}`)
        this.$noty[data.type](data.message)
        this.title = this.learningTreeForm.title
        this.description = this.learningTreeForm.description
        this.public = this.learningTreeForm.public
        this.notes = this.learningTreeForm.notes
        this.resetLearningTreeModal('modal-learning-tree-properties')
      } catch (error) {
        if (!error.message.includes('status code 422')) {
          this.$noty.error(error.message)
        } else {
          this.allFormErrors = this.learningTreeForm.errors.flatten()
          this.$bvModal.show('modal-form-errors-learning-tree')
        }
      }
    },
    async getLearningTreeLearningTreeId (learningTreeId) {
      try {
        const { data } = await axios.get(`/api/learning-trees/${learningTreeId}`)
        this.title = data.title
        this.description = data.description
        this.public = data.public
        this.notes = data.notes
        this.assessmentQuestionId = data.question_id
        this.canUndo = data.can_undo
        this.isAuthor = data.author_id === this.user.id
        if (data.learning_tree) {
          let learningTree = data.learning_tree.replaceAll('/assets/img', this.asset('assets/img'))
          flowy.import(JSON.parse(learningTree))
        }
      } catch (error) {
        this.$noty.error(error.message)
      }
    },
    updateBorders (questionTypes) {
      $('input[name="question_id"]').each(function () {
        let questionId = parseInt($(this).val())
        let classToAdd
        switch (questionTypes[questionId]) {
          case ('completed'):
            classToAdd = 'completed-border'
            break
          case ('not-completed'):
            classToAdd = 'non-completed-border'
            break
          case ('assessment'):
            classToAdd = 'question-border'
            break
          case ('exposition'):
            classToAdd = 'exposition-border'
            break
          default:
            classToAdd = 'empty-node-border'
        }
        let div = $(this).parent('div')
        div.removeClass('question-border exposition-border empty-node-border').addClass(classToAdd)
      })
    },
    async saveLearningTree () {
      if (!this.isAuthor) {
        return false
      }
      try {
        let questionIds = this.getQuestionIdsFromNodes()
        let learningTree = JSON.stringify(flowy.output()).replaceAll(this.asset('assets/img'), '/assets/img')
        const { data } = await axios.patch(`/api/learning-trees/${this.learningTreeId}`, {
          'learning_tree': learningTree,
          question_ids: questionIds
        })
        if (data.type === 'no_change') {
          return false
        }
        this.updateBorders(data.question_types)
        if (data.type === 'success') {
          if (document.getElementById('blocklist')) {
            document.getElementById('blocklist').innerHTML = ''
          }
          this.nodeIsPending = false
          this.questionId = ''
        }
        this.$noty[data.type](data.message)
        this.canUndo = data.can_undo
        this.updateCanvasHeight()
      } catch (error) {
        this.$noty.error(error.message)
      }
    },
    getQuestionIdsFromNodes () {
      let html = $.parseHTML(flowy.output().html)
      let questionIds = []
      $(html).find('.question_id').each(function () {
        questionIds.push(parseInt($(this).text()))
      })
      return questionIds
    },
    getBlockyNameHTML (questionId) {
      return `<span class="question_id">${questionId}</span>`
    },
    async addRemediation () {
      let isRootNode = typeof flowy.output() === 'undefined'
      let title = ''
      let question
      question = await this.validateAssignmentAndQuestionId(this.questionId, isRootNode)
      if (question) {
        title = question.title ? question.title : 'None'
        this.questionId = question.id
      }
      if (!title) {
        return false
      }

      this.nodeIsPending = true

      let blockElems = document.querySelectorAll('div.blockelem.create-flowy.noselect')
      let blockyNameHTML = this.getBlockyNameHTML(this.questionId)
      let borderClass = 'empty-node-border'
      if (question && !question.empty_learning_tree_node) {
        borderClass = question.question_type === 'assessment' ? 'question-border' : 'exposition-border'
      }
      let newBlockElem = `<div class="blockelem create-flowy noselect ${borderClass}">
        <input type="hidden" name="blockelemtype" class="blockelemtype" value="${blockElems.length + 2}">
        <input type="hidden" name="question_id" value="${this.questionId}">
        <div class="grabme"></div>
        <div class="blockin">
          <span class="blockyname"> ${blockyNameHTML} </span>
          <div class="blockin-info">
            <span class="blockdesc"><span class="title">${title}</span>
            <span class="extra"></span>
          </div>
        </div>
      </div>`

      await this.$nextTick()
      const blocklist = document.getElementById('blocklist')
      if (blocklist) {
        blocklist.innerHTML = newBlockElem
      }
      // Scroll canvas to top so drop targets are visible
      await this.$nextTick()
      const canvas = document.getElementById('canvas')
      if (canvas) {
        canvas.scrollTop = 0
        this.syncBlockPositions()
      }
      this.questionId = ''
    }
  }
}
</script>

<style>
/* =====================
   LAYOUT
   ===================== */
body, html {
  margin: 0;
  padding: 0;
  overflow: hidden;
  height: 100%;
}

.learning-tree-editor {
  display: flex;
  flex-direction: column;
  height: calc(100vh - var(--editor-offset, 0px));
  overflow: hidden;
}

/* =====================
   TOOLBAR
   ===================== */
#toolbar {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 8px 16px;
  background-color: #F8F8F8;
  border-bottom: 1px solid #E8E8EF;
  height: 48px;
  box-sizing: border-box;
  flex-shrink: 0;
  z-index: 10;
}

.toolbar-icon {
  font-size: 1.1rem;
  color: #393C44;
  cursor: pointer;
  transition: opacity .2s;
}

.toolbar-icon:hover {
  opacity: .6;
}

.toolbar-icon.disabled {
  opacity: .3;
  cursor: not-allowed;
}

.toolbar-btn {
  font-size: 13px;
}

/* =====================
   STAGING AREA
   ===================== */
#staging-area {
  display: flex;
  align-items: center;
  gap: 16px;
  padding: 8px 16px;
  background-color: #EEF4FF;
  border-bottom: 1px solid #C5D8FF;
  min-height: 48px;
  box-sizing: border-box;
  flex-shrink: 0;
  z-index: 10;
}

.staging-hint {
  font-family: Roboto, sans-serif;
  font-size: 13px;
  color: #217CE8;
  font-weight: 500;
  white-space: nowrap;
}

#blocklist {
  display: flex;
  align-items: center;
  gap: 8px;
}

/* =====================
   CANVAS
   ===================== */
#canvas {
  position: relative;
  width: 100%;
  flex: 1;
  min-height: 0;
  overflow: scroll;
  z-index: 0;
  background-repeat: repeat;
  background-size: 30px 30px;
}

#canvas::after {
  content: '';
  display: block;
  position: absolute;
  top: 0;
  left: 0;
  width: 1px;
  height: 3000px;
}

/* =====================
   BLOCKS
   ===================== */
.blockelem:first-child {
  margin-top: 20px;
}

.blockelem {
  padding-top: 10px;
  margin-bottom: 5px;
  width: 242px;
  border: 1px solid transparent;
  transition-property: box-shadow, height;
  transition-duration: .2s;
  transition-timing-function: cubic-bezier(.05, .03, .35, 1);
  border-radius: 5px;
  box-shadow: 0px 0px 30px rgba(22, 33, 74, 0);
  box-sizing: border-box;
  background: #FFFFFF;
}

.blockelem:hover {
  box-shadow: 0px 4px 30px rgba(22, 33, 74, 0.08);
  border-radius: 5px;
  background-color: #FFF;
  cursor: pointer;
}

.blocktext {
  display: inline-block;
  width: 220px;
  vertical-align: top;
  margin-left: 12px;
}

.blocktitle {
  margin: 0px !important;
  padding: 0px !important;
  font-family: Roboto, sans-serif;
  font-weight: 500;
  font-size: 16px;
  color: #393C44;
}

.blockdesc, .blockyinfo, .blockin-info {
  font-family: Roboto, sans-serif;
  color: #808292;
  font-size: 14px;
  line-height: 21px;
}

.blockyinfo, .blockin-info {
  height: 60px;
  margin-bottom: 10px;
  margin-left: 10px;
  margin-right: 10px;
  overflow: hidden;
  text-overflow: ellipsis;
  display: -webkit-box;
  -webkit-line-clamp: 3;
  line-clamp: 3;
  -webkit-box-orient: vertical;
}

.blockdisabled {
  background-color: #F0F2F9;
  opacity: .5;
}

.blockyname {
  display: none;
}

.blockyright {
  display: inline-block;
  float: right;
  vertical-align: middle;
  margin-right: 20px;
  margin-top: 10px;
  width: 28px;
  height: 28px;
  border-radius: 5px;
  text-align: center;
  background-color: #FFF;
  transition: all .3s cubic-bezier(.05, .03, .35, 1);
  z-index: 10;
}

.blockyright:hover {
  background-color: #F1F4FC;
  cursor: pointer;
}

.block {
  background-color: #FFF;
  margin-top: 0px !important;
  box-shadow: 0px 4px 30px rgba(22, 33, 74, 0.05);
  position: absolute;
  z-index: 9;
}

.selectedblock {
  border: 2px solid #217CE8;
  box-shadow: 0px 4px 30px rgba(22, 33, 74, 0.08);
}

.dragging {
  z-index: 111 !important;
}

/* =====================
   INDICATOR
   ===================== */
.indicator {
  width: 12px;
  height: 12px;
  border-radius: 60px;
  background-color: #217ce8;
  margin-top: -5px;
  opacity: 1;
  transition: all .3s cubic-bezier(.05, .03, .35, 1);
  transform: scale(1);
  position: absolute;
  z-index: 2;
}

.invisible {
  opacity: 0 !important;
  transform: scale(0);
}

.indicator:after {
  content: "";
  display: block;
  width: 12px;
  height: 12px;
  background-color: #217ce8;
  transform: scale(1.7);
  opacity: .2;
  border-radius: 60px;
}

/* =====================
   ARROWS
   ===================== */
.arrowblock {
  position: absolute;
  width: 100%;
  overflow: visible;
  pointer-events: none;
}

.arrowblock svg {
  width: -webkit-fill-available;
  overflow: visible;
}

/* =====================
   BORDER VARIANTS
   ===================== */
.empty-node-border {
  border: 2px solid darkgray;
}

.question-border {
  border: 2px solid cornflowerblue;
}

.exposition-border {
  border: 2px solid rosybrown;
}

.completed-border {
  border: 2px solid #008600;
}

.non-completed-border {
  border: 2px solid #dc3545;
}

/* =====================
   MODAL BORDERS
   ===================== */
.modal-border-blue .modal-content {
  border: 3px solid cornflowerblue;
}

.modal-border-red .modal-content {
  border: 3px solid rosybrown;
}

.modal-border-gray .modal-content {
  border: 3px solid darkgray;
}

.modal .modal-90 {
  max-width: 90%;
  width: 90%;
}

.modal .modal-90 .modal-body {
  padding: 0;
  max-height: 85vh;
  overflow-y: auto;
}

/* =====================
   MISC
   ===================== */
.blockyinfo span.remediation-info {
  border-bottom: none;
  color: #808292;
}

.noselect {
  -webkit-touch-callout: none;
  -webkit-user-select: none;
  -khtml-user-select: none;
  -moz-user-select: none;
  -ms-user-select: none;
  user-select: none;
}

/* =====================
   FONTS
   ===================== */
@font-face {
  font-family: 'Roboto';
  font-style: normal;
  font-weight: 400;
  font-display: swap;
  src: local('Roboto'), local('Roboto-Regular'), url(https://fonts.gstatic.com/s/roboto/v20/KFOmCnqEu92Fr1Mu4mxKKTU1Kg.woff2) format('woff2');
  unicode-range: U+0000-00FF, U+0131, U+0152-0153, U+02BB-02BC, U+02C6, U+02DA, U+02DC, U+2000-206F, U+2074, U+20AC, U+2122, U+2191, U+2193, U+2212, U+2215, U+FEFF, U+FFFD;
}

@font-face {
  font-family: 'Roboto';
  font-style: normal;
  font-weight: 500;
  font-display: swap;
  src: local('Roboto Medium'), local('Roboto-Medium'), url(https://fonts.gstatic.com/s/roboto/v20/KFOlCnqEu92Fr1MmEU9fBBc4AMP6lQ.woff2) format('woff2');
  unicode-range: U+0000-00FF, U+0131, U+0152-0153, U+02BB-02BC, U+02C6, U+02DA, U+02DC, U+2000-206F, U+2074, U+20AC, U+2122, U+2191, U+2193, U+2212, U+2215, U+FEFF, U+FFFD;
}

@font-face {
  font-family: 'Roboto';
  font-style: normal;
  font-weight: 700;
  font-display: swap;
  src: local('Roboto Bold'), local('Roboto-Bold'), url(https://fonts.gstatic.com/s/roboto/v20/KFOlCnqEu92Fr1MmWUlfBBc4AMP6lQ.woff2) format('woff2');
  unicode-range: U+0000-00FF, U+0131, U+0152-0153, U+02BB-02BC, U+02C6, U+02DA, U+02DC, U+2000-206F, U+2074, U+20AC, U+2122, U+2191, U+2193, U+2212, U+2215, U+FEFF, U+FFFD;
}

</style>
