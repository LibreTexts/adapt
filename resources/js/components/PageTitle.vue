<template>
  <div class="margin-top: -10px">
    <b-row>
      <b-col>
        <h1 style="font-size: 26px; line-height: 1.1;" class="page-title mb-0 text-primary font-weight-normal">
          <span v-show="openEndedQuestionInRealTimeAssignment">
            <b-icon
              id="delayed-in-real-time-tooltip"
              variant="danger"
              icon="exclamation-triangle"
              style="cursor:pointer;"
            />
            <b-tooltip target="delayed-in-real-time-tooltip"
                       delay="500"
                       triggers="hover focus"
            >
              This question is an open-ended question in a real time assignment. These types of questions are
              typically used in delayed assignments.
            </b-tooltip>
            <b-icon
              v-if="learningTreeId"
              icon="tree"
              variant="success"
            />
          </span>
          <b-icon
            v-if="assessmentType === 'learning tree' || learningTreeId"
            icon="tree"
            variant="success"
          />{{ title }}
          <CustomTitle v-if="title && showPencil"
                       :assignment-id="assignmentId"
                       :question-id="questionId"
                       :title="title"
                       style="margin-left:-7px"
                       pencil-class="mb-1"
                       @updateCustomQuestionTitle="updateCustomQuestionTitle"
          />
          <AlgorithmicIcon :algorithmic-question="algorithmicQuestion"
                           :algorithmic-assignment="algorithmicAssignment"
                           :is-instructor-with-anonymous-view="isInstructorWithAnonymousView"
                           :is-title="true"
          />
          <FormativeWarning v-if="showFormativeWarning && title"
                            :formative-question="true"
          />
        </h1>
        <small class="text-muted">
        <span v-if="adaptId">ID: <span id="adapt-id">{{ adaptId }}</span>  <span class="text-info">
          <a href=""
             aria-label="Copy ADAPT ID"
             @click.prevent="doCopy('adapt-id')"
          >
            <font-awesome-icon :icon="copyIcon"/>
          </a>
        </span>
        </span>
          <span v-if="learningTreeId"><br>Learning Tree ID: <span id="learning-tree-id">{{
              learningTreeId
            }}</span>  <span
            class="text-info"
          >
          <a href=""
             aria-label="Copy Learning Tree ID"
             @click.prevent="doCopy('learning-tree-id')"
          >
            <font-awesome-icon :icon="copyIcon"/>
          </a>
        </span>

        </span>
        </small>
      </b-col>
      <b-col v-if="user.role === 2" cols="auto" class="text-right"/>
      <div id="instructor-action-icons"
           style="display: flex;align-items: flex-end; padding-bottom: 0 !important;gap: 0.25rem; "
      >
      </div>
    </b-row>
    <hr style="margin-top:7px">
  </div>
</template>

<script>
import { faCopy } from '@fortawesome/free-regular-svg-icons'
import { doCopy } from '~/helpers/Copy'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import FormativeWarning from './FormativeWarning.vue'
import CustomTitle from './CustomTitle.vue'
import AlgorithmicIcon from './AlgorithmicIcon.vue'
import { mapGetters } from 'vuex'


export default {
  name: 'PageTitle',
  components: {
    CustomTitle,
    FormativeWarning,
    FontAwesomeIcon,
    AlgorithmicIcon
  },
  props: {
    openEndedQuestionInRealTimeAssignment: {
      type: Boolean,
      default: false
    },
    isInstructorWithAnonymousView: {
      type: Boolean,
      default: false
    },
    algorithmicQuestion: {
      type: Boolean,
      default: false
    },
    algorithmicAssignment: {
      type: Boolean,
      default: false
    },
    title: {
      type: String,
      default: ''
    },
    adaptId: {
      type: String,
      default: ''
    },
    learningTreeId: {
      type: String,
      default: ''
    },
    showFormativeWarning: {
      type: Boolean,
      default: false
    },
    showPencil: {
      type: Boolean,
      default: false
    },
    cloneSourceId: {
      type: Number,
      default: 0
    },
    assignmentId: {
      type: Number,
      default: 0
    },
    questionId: {
      type: Number,
      default: 0
    },
    assessmentType: {
      type: String,
      default: ''
    }
  },
  data: () => ({
    copyIcon: faCopy
  }),
  computed: mapGetters({
    user: 'auth/user'
  }),
  methods: {
    doCopy,
    updateCustomQuestionTitle (title) {
      this.$emit('updateCustomQuestionTitle', title)
    }
  }
}
</script>
