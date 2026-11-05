<template>
  <div>
    <b-modal id="modal-confirm-delete-rubric-criterion"
             title="Delete Criterion"
             size="lg"
    >
      <p>Please confirm that you would like to delete the rubric criterion:</p>
      <p class="font-weight-bold">
        {{ rubricItemToDelete.title }}
      </p>
      <template #modal-footer="{ cancel, ok }">
        <b-button size="sm" @click="$bvModal.hide('modal-confirm-delete-rubric-criterion')">
          Cancel
        </b-button>
        <b-button size="sm"
                  variant="danger"
                  @click="deleteRubricCriterion()"
        >
          Delete
        </b-button>
      </template>
    </b-modal>
    <b-modal id="modal-dynamic-rubric-points-info"
             title="When the Criteria Don't Add Up to the Question's Points"
             size="lg"
             ok-only
             ok-title="Got it"
             button-size="sm"
    >
      <p>
        In a dynamic rubric, each criterion is worth exactly its points, and a student's score is capped at the
        question's points (less any auto-graded part of the question). Most of the time the criteria should add up to
        the question's points, and a mismatch is a typo. There are a few good reasons for them not to:
      </p>
      <p class="font-weight-bold mb-1">
        More than the question's points
      </p>
      <ul>
        <li>
          <strong>Alternative routes.</strong> "Any 3 of these 4 steps earns full marks": four 4-point criteria on a
          12-point question. A student with all four still gets 12.
        </li>
        <li>
          <strong>Bonus criteria.</strong> Extra credit for going further, without letting anyone go past full marks.
        </li>
      </ul>
      <p class="font-weight-bold mb-1">
        Less than the question's points
      </p>
      <ul>
        <li>
          <strong>Part of the question is auto-graded.</strong> If 4 of the 10 points come from an auto-graded answer,
          criteria adding up to 6 cover the rest.
        </li>
        <li>
          <strong>The rest is given by judgement.</strong> Graders add the remaining points with the adjustment box.
        </li>
      </ul>
      <p class="font-weight-bold mb-1">
        0-point criteria
      </p>
      <ul>
        <li>
          <strong>Feedback only.</strong> "Check your units" tells the student something without changing the score.
        </li>
      </ul>
      <p class="mb-0">
        If none of these apply, use <strong>Scale criteria</strong> to make the criteria add up to the question's
        points.
      </p>
    </b-modal>
    <b-form-group
      v-if="canUseTemplates && rubricTemplateOptions.length"
      label-for="rubric_template"
      :label-cols-sm="isTemplate ? 2 : 3"
      :label-cols-lg="isTemplate ? 1 : 2"
      :label="isTemplate ? 'Template' : 'Load Rubric Template'"
      label-size="sm"
    >
      <b-form-row>
        <b-col lg="4">
          <b-form-select v-model="rubricTemplate"
                         :options="rubricTemplateOptions"
                         size="sm"
                         :disabled="dynamicScoresExist || isConverting"
                         @change="getRubricTemplate($event)"
          />
        </b-col>
      </b-form-row>
      <small v-if="dynamicScoresExist" class="text-muted">
        You can still edit the criteria below: when you save, you'll see whose scores change before anything is saved.
        Loading a template isn't available here, because a template replaces every criterion, and every graded student
        would lose the criteria they were given.
      </small>
    </b-form-group>
    <b-form-group
      v-show="!isTemplate && canUseTemplates && !isConverting"
      label-cols-sm="3"
      label-cols-lg="2"
      label="On save"
      label-size="sm"
      label-for="save_options"
    >
      <template #label>
        On save
        <QuestionCircleTooltip
          id="on-save-tooltip"
        />
        <b-tooltip target="on-save-tooltip"
                   delay="250"
                   triggers="hover focus"
        >
          If you choose "Yes", it will be saved to "My Rubric Templates", accessible in your Dashboard.
        </b-tooltip>
      </template>
      <b-form-radio-group
        id="save_options"
        v-model="rubricTemplateSaveOption"
        class="mt-2"
      >
        <b-form-radio value="do not save as template">
          Do not save as template
        </b-form-radio>
        <b-form-radio value="save as new template">
          Save as new template
        </b-form-radio>
        <b-form-radio v-if="rubricTemplate" value="update existing template">
          Update existing template
        </b-form-radio>
      </b-form-radio-group>
    </b-form-group>
    <b-form-group
      label-cols-sm="3"
      label-cols-lg="2"
      label-size="sm"
      label-for="rubric_shown"
    >
      <template #label>
        Rubric Shown
        <QuestionCircleTooltip
          id="rubric-shown-tooltip"
        />
        <b-tooltip target="rubric-shown-tooltip"
                   delay="250"
                   triggers="hover focus"
        >
          If you choose `No` then your students will not see the breakdown of the score.
        </b-tooltip>
      </template>
      <b-form-radio-group
        id="rubric_shown"
        v-model="rubricShown"
        class="mt-2"
        :disabled="!canChangeRubricShown || isConverting"
      >
        <b-form-radio :value="true">
          Yes
        </b-form-radio>
        <b-form-radio :value="false">
          No
        </b-form-radio>
      </b-form-radio-group>
    </b-form-group>
    <b-form-group
      label-cols-sm="3"
      label-cols-lg="2"
      label-size="sm"
      label-for="scoring_style"
    >
      <template #label>
        Scoring Style
        <QuestionCircleTooltip
          id="scoring-style-tooltip"
        />
        <b-tooltip target="scoring-style-tooltip"
                   delay="250"
                   triggers="hover focus"
        >
          Static: the points are weights that scale to the question's points, and graders enter points for each
          criterion. Dynamic: each criterion is worth exactly its points and graders apply criteria (keys 1-9); if you
          later change a criterion, every student who has it is rescored.
        </b-tooltip>
      </template>
      <b-form-radio-group
        id="scoring_style"
        v-model="scoringStyle"
        class="mt-2"
      >
        <b-form-radio value="static" :disabled="!canChangeScoringStyle">
          Static
        </b-form-radio>
        <b-form-radio value="dynamic" :disabled="!canChangeScoringStyle">
          Dynamic
        </b-form-radio>
      </b-form-radio-group>
      <small v-if="!canChangeScoringStyle || !canChangeRubricShown" class="text-muted d-block">
        Some of these options need your instructor's permission.
      </small>
      <small v-if="dynamicScoresExist && canChangeScoringStyle && !isConverting" class="text-muted d-block">
        Students have been graded with this dynamic rubric. Choosing Static converts their scores, and you'll see a
        preview before anything is saved.
      </small>
      <small v-if="staticScoresExist && canChangeScoringStyle && !isConverting" class="text-muted d-block">
        Students already have static rubric scores for this question. Choosing Dynamic converts their scores, and
        you'll see a preview before anything is saved.
      </small>
      <b-alert v-if="conversionDirection === 'to_dynamic'" show variant="info" class="mt-2 mb-0 small">
        <strong>Converting to dynamic scoring.</strong> Each criterion becomes worth its points in this question, and
        a criterion a student got full points on counts as applied. When you save, you'll see whose scores would change
        and choose what happens to them. The rubric is converted as it was saved: you can edit the criteria once it's
        converted.
      </b-alert>
      <b-alert v-if="conversionDirection === 'to_static'" show variant="info" class="mt-2 mb-0 small">
        <strong>Converting to static scoring.</strong> What this means:
        <ul class="mb-1 mt-1">
          <li>
            Each criterion's points become a weight, and static rubrics scale the weights to the question's points.
            A student gets full points on each criterion they had applied and 0 on the rest.
          </li>
          <li>
            Static rubrics have no adjustment.
            <QuestionCircleTooltip id="static-conversion-adjustment-tooltip"/>
            <b-tooltip target="static-conversion-adjustment-tooltip"
                       delay="250"
                       triggers="hover focus"
            >
              Example: Sam got "Correct arrow" (6 points) plus a +2 adjustment, for 8/10. A static breakdown can only
              show criterion points, so Sam's would show 6: the +2 has nowhere to go. If you keep Sam's score, it stays
              8/10 and Sam is marked "Needs review" so a grader can check the points.
            </b-tooltip>
            Students with an adjustment, or whose criteria were capped at the
            question's points, may not keep the same score: the preview lists them, and you choose to keep their score
            (marked "Needs review") or clear it.
          </li>
          <li v-if="zeroPointCriteriaTitles.length">
            Static rubrics don't allow 0-point criteria, so these feedback-only criteria will be removed:
            <strong>{{ zeroPointCriteriaTitles.join(', ') }}</strong>. No score changes because of this.
          </li>
          <li>
            Graders won't be able to use the dynamic keys (1-9, 0) or adjustments on this question any more.
          </li>
        </ul>
        Nothing is saved until you confirm the preview. The rubric is converted as it was saved: you can edit the
        criteria once it's converted.
      </b-alert>
    </b-form-group>
    <b-form-group
      v-if="isTemplate || ['save as new template','update existing template'].includes(rubricTemplateSaveOption)"
      label-cols-sm="3"
      label-cols-lg="2"
      label-for="template_name"
      label="Name*"
    >
      <b-form-row>
        <b-form-input
          id="name"
          v-model="name"
          required
          type="text"
          :class="{ 'is-invalid': errors.name}"
          @keydown="errors.name = ''"
        />
        <ErrorMessage v-if="errors.name" :message="errors.name[0]"/>
      </b-form-row>
    </b-form-group>
    <b-form-group
      v-if="isTemplate || ['save as new template','update existing template'].includes(rubricTemplateSaveOption)"
      label-cols-sm="3"
      label-cols-lg="2"
      label-for="description"
      label="Description*"
    >
      <b-form-row>
        <b-form-textarea
          id="description"
          v-model="description"
          required
          type="text"
          :class="{ 'is-invalid': errors.description}"
          @keydown="errors.description = ''"
        />
        <ErrorMessage v-if="errors.description" :message="errors.description[0]"/>
      </b-form-row>
    </b-form-group>
    <div v-if="rubricItems.length">
      <RequiredText/>

      <table class="table table-striped small" aria-label="Rubric Criteria">
        <thead>
        <tr>
          <th scope="col" style="width:40px">
            <span class="sr-only">Reorder or delete</span>
          </th>
          <th scope="col">
            Title*
          </th>
          <th scope="col">
            Description (Optional)
          </th>
          <th scope="col" style="width:110px">
            Points
          </th>
        </tr>
        </thead>
        <tbody is="draggable"
               v-model="rubricItems"
               tag="tbody"
               handle=".rubric-criterion-drag-handle"
        >
        <tr
          v-for="( rubricItem,rubricItemIndex) in rubricItems"
          :key="`rubric-criterion-${rubricItemKey(rubricItem)}`"
        >
          <td class="text-nowrap">
            <span v-if="!isConverting">
              <b-icon icon="grip-vertical"
                      class="rubric-criterion-drag-handle text-muted"
                      style="cursor: move;"
                      :title="'Drag to reorder'"
                      aria-hidden="true"
              />
              <b-icon icon="trash" style="cursor: pointer;" @click="confirmDeleteCriterion(rubricItem)"/>
            </span>
          </td>
          <td>
            <b-form-input
              v-model="rubricItem.title"
              :disabled="isConverting"
              type="text"
              placeholder=""
              required
              style="width:100%"
              size="sm"
              :class="{ 'is-invalid': errors.rubric_items && errors.rubric_items[rubricItemIndex] && errors.rubric_items[rubricItemIndex].title !== 'passes'}"
              @keydown="errors.rubric_items && errors.rubric_items[rubricItemIndex] ? errors.rubric_items[rubricItemIndex].title = 'passes': ''"
            />
            <ErrorMessage
              v-if="errors.rubric_items && errors.rubric_items[rubricItemIndex] && errors.rubric_items[rubricItemIndex].title !== 'passes'"
              :message="errors.rubric_items[rubricItemIndex].title"
            />
          </td>
          <td>
            <b-form-textarea
              v-model="rubricItem.description"
              :disabled="isConverting"
              type="text"
              placeholder=""
              rows="2"
              required
              size="sm"
              style="width:100%"
              :class="{ 'is-invalid': errors.rubric_items && errors.rubric_items[rubricItemIndex] && errors.rubric_items[rubricItemIndex].description && errors.rubric_items[rubricItemIndex].description !== 'passes'}"
              @keydown="errors.rubric_items && errors.rubric_items[rubricItemIndex] ? errors.rubric_items[rubricItemIndex].description = 'passes' : ''"
            />
            <ErrorMessage
              v-if="errors.rubric_items && errors.rubric_items[rubricItemIndex] && errors.rubric_items[rubricItemIndex].description && errors.rubric_items[rubricItemIndex].description !== 'passes'"
              :message="errors.rubric_items[rubricItemIndex].description"
            />
          </td>
          <td>
            <b-form-input
              v-model="rubricItem.points"
              :disabled="isConverting"
              type="text"
              size="sm"
              style="width:70px"
              placeholder=""
              required
              :class="{ 'is-invalid': errors.rubric_items && errors.rubric_items[rubricItemIndex] && errors.rubric_items[rubricItemIndex].points !== 'passes'}"
              @keydown="errors.rubric_items && errors.rubric_items[rubricItemIndex] ? errors.rubric_items[rubricItemIndex].points = 'passes' : ''"
            />
            <ErrorMessage
              v-if="errors.rubric_items && errors.rubric_items[rubricItemIndex] && errors.rubric_items[rubricItemIndex].points !== 'passes'"
              :message="errors.rubric_items[rubricItemIndex].points"
            />
          </td>
        </tr>
        </tbody>
        <tfoot>
        <tr>
          <td/>
          <td/>
          <td/>
          <td class="font-weight-bold">
            Total: <span>{{ roundedRunningTotal() }}</span> <span v-show="assignmentId && scoringStyle === 'static'"><QuestionCircleTooltip
            id="running-total-tooltip"
          />
        <b-tooltip target="running-total-tooltip"
                   delay="250"
                   triggers="hover focus"
        >
          After saving, the points in each criterion will automatically scale to {{ questionPoints}} point<span v-show="questionPoints>1">s</span> in order to match the total number of points in this question.
        </b-tooltip></span>
          </td>
        </tr>
        </tfoot>
      </table>
      <small v-if="rubricItems.length > 1 && !isConverting" class="text-muted d-block mb-2">
        Drag <b-icon icon="grip-vertical" aria-hidden="true"/> to reorder the criteria.
        <span v-if="scoringStyle === 'dynamic'">Keys 1-9 and 0 in the Open Grader follow this order.</span>
      </small>
      <div v-if="scoringStyle === 'static' && !assignmentId" class="small mb-2">
        Points are weights. In each assignment they're scaled to the question's points<span
          v-if="staticWeightsExample">: on a 10-point question, these would be {{ staticWeightsExample }}</span>.
      </div>
      <div v-if="scoringStyle === 'dynamic' && !isConverting" class="small mb-2">
        <span v-if="!assignmentId">
          Points are exact. Each assignment sets this question's points, and scores are capped there.
          <b-link @click="$bvModal.show('modal-dynamic-rubric-points-info')">
            Should the criteria add up to the question's points?
          </b-link>
        </span>
        <b-alert v-else-if="questionPoints && !totalMatchesQuestionPoints()" variant="warning" show class="mb-0">
          The criteria add up to {{ roundedRunningTotal() }}; this question is worth {{ questionPoints }}.
          Scores are capped at {{ questionPoints }}.
          <b-link @click="$bvModal.show('modal-dynamic-rubric-points-info')">
            <b-icon icon="question-circle" aria-hidden="true"/> Why might they differ?
          </b-link>
          <b-button size="sm" variant="outline-primary" class="ml-2" @click="scaleItemsToQuestionPoints()">
            Scale criteria to {{ questionPoints }}
          </b-button>
        </b-alert>
      </div>
    </div>
    <p v-if="!isConverting">
      <b-button variant="info" class="mr-1"
                size="sm" @click="addRubricItemRow"
      >
        Add Criterion
      </b-button>
    </p>
    <b-alert variant="warning" :show="needsGradedStaticPermission">
      Students have already been graded with this rubric, so changing its criteria needs your instructor's permission.
    </b-alert>
    <b-alert variant="danger" :show="rubricPointsBreakdownExists && scoringStyle === 'static' && !needsGradedStaticPermission">
      You have already saved the point breakdowns for at least one student. If you update this rubric, all rubric point
      breakdowns
      will be reset and you will
      have to enter new scores for your students.
    </b-alert>
    <b-alert variant="info" :show="dynamicScoresExist && !isConverting">
      Students have been graded with this rubric. When you save, you'll see whose scores change before anything is
      saved.
    </b-alert>
  </div>
</template>

<script>
import axios from 'axios'
import draggable from 'vuedraggable'
import { mapGetters } from 'vuex'
import ErrorMessage from './ErrorMessage.vue'

// row keys for criteria that don't have an id yet; kept off the criteria so they're never saved
const rubricItemKeys = new WeakMap()
let nextRubricItemKey = 0

export default {
  name: 'RubricProperties',
  components: {
    ErrorMessage,
    draggable
  },
  props: {
    errors: {
      type: Object,
      default: () => ({
        name: ''
      })
    },
    assignmentId: {
      type: Number,
      default: 0
    },
    questionId: {
      type: Number,
      default: 0
    },
    questionPoints: {
      type: Number,
      default: 0
    },
    isTemplate: {
      type: Boolean,
      default: false
    },
    isEdit: {
      type: Boolean,
      default: false
    },
    rubricInfo: {
      type: Object,
      default: () => {
      }
    },
    // a grader's options from the dynamic-rubric show endpoint; the server enforces them too
    rubricPermissions: {
      type: Object,
      default: null
    }
  },
  watch: {
    'errors.rubric_name': function (newVal){
      this.errors.name = newVal
    },
    'errors.rubric_description' : function (newVal){
      this.errors.description = newVal
    },
    rubricTemplate: function (newVal) {
      this.$emit('setKeyValue', 'rubricTemplate', newVal)
    },
    rubricTemplateSaveOption: function (newVal) {
      this.$emit('setKeyValue', 'rubricTemplateSaveOption', newVal)
    },
    description: function (newVal) {
      this.$emit('setKeyValue', 'description', newVal)
    },
    rubricItems: function (newVal) {
      this.$emit('setKeyValue', 'rubricItems', newVal)
      this.$emit('setKeyValue', 'rubricShown', this.rubricShown)
      this.$emit('setKeyValue', 'rubricTemplateSaveOption', this.rubricTemplateSaveOption)
    },
    runningTotal: function (newVal) {
      this.$emit('setKeyValue', 'runningTotal', newVal)
    },
    name: function (newVal) {
      this.$emit('setKeyValue', 'name', newVal)
    },
    rubricShown: function (newVal) {
      this.$emit('setKeyValue', 'rubricShown', newVal)
    },
    conversionDirection: function (newVal) {
      this.$emit('setKeyValue', 'rubricConversionDirection', newVal)
    },
    isConverting: function (newVal) {
      this.$emit('setKeyValue', 'rubricConverting', newVal)
      if (newVal) {
        this.restoreSavedRubricForConversion()
      }
    },
    scoringStyle: function (newVal, oldVal) {
      this.$emit('setKeyValue', 'rubricScoringStyle', newVal)
      // converting a graded dynamic rubric keeps its points as the weights (the preview shows them scaled)
      if (newVal === 'static' && oldVal === 'dynamic' && !this.dynamicScoresExist) {
        // static points are weights scaled to the question's points
        this.scalePoints()
      }
    }
  },
  computed: {
    ...mapGetters({
      user: 'auth/user'
    }),
    // only instructors have rubric templates; graders editing from the Open Grader don't
    canUseTemplates () {
      return this.isTemplate || !this.user || +this.user.role !== 4
    },
    isRestrictedGrader () {
      return !this.isTemplate && Boolean(this.user) && +this.user.role === 4
    },
    // graders may always change the criteria; these need the options the instructor turned on
    canChangeScoringStyle () {
      if (!this.isRestrictedGrader) {
        return true
      }
      const permissions = this.rubricPermissions || {}
      if (!permissions.can_change_scoring_style) {
        return false
      }
      return !this.rubricPointsBreakdownExists || Boolean(permissions.can_convert_graded_rubrics)
    },
    // saving new criteria would reset these students' point breakdowns; the server refuses it without this option
    needsGradedStaticPermission () {
      return this.isRestrictedGrader && this.staticScoresExist &&
        !(this.rubricPermissions || {}).can_edit_graded_static_rubrics
    },
    canChangeRubricShown () {
      return !this.isRestrictedGrader || Boolean((this.rubricPermissions || {}).can_change_rubric_shown)
    },
    // only in an assignment: the rubric in use already has student scores of one style or the other
    dynamicScoresExist () {
      return !this.isTemplate && Boolean(this.assignmentId) && this.rubricPointsBreakdownExists && this.initialScoringStyle === 'dynamic'
    },
    // a static rubric with static scores being switched to dynamic: saved through the conversion preview
    // a rubric with student scores switched to the other style: saved through a conversion preview
    conversionDirection () {
      if (this.staticScoresExist && this.scoringStyle === 'dynamic') {
        return 'to_dynamic'
      }
      if (this.dynamicScoresExist && this.scoringStyle === 'static') {
        return 'to_static'
      }
      return ''
    },
    isConverting () {
      return Boolean(this.conversionDirection)
    },
    // question bank and templates: what the static weights become on a 10-point question (rounded like the grader)
    staticWeightsExample () {
      const weights = this.rubricItems.map(item => +item.points)
      if (!weights.length || weights.some(weight => isNaN(weight) || weight < 0)) {
        return ''
      }
      const total = weights.reduce((sum, weight) => sum + weight, 0)
      if (!(total > 0)) {
        return ''
      }
      const scaled = weights.map(weight => Math.round(weight * 10 / total * 100) / 100)
      return scaled.length === 1
        ? `${scaled[0]}`
        : `${scaled.slice(0, -1).join(', ')} and ${scaled[scaled.length - 1]}`
    },
    zeroPointCriteriaTitles () {
      return (this.savedRubricItems || []).filter(item => !(+item.points > 0)).map(item => item.title)
    },
    staticScoresExist () {
      return !this.isTemplate && Boolean(this.assignmentId) && this.rubricPointsBreakdownExists && this.initialScoringStyle === 'static'
    }
  },
  data: () => ({
    rubricShown: true,
    scoringStyle: 'static',
    initialScoringStyle: 'static',
    runningTotal: 0,
    initialScoreInputType: '',
    rubricPointsBreakdownExists: false,
    rubricItemToDelete: {},
    rubricTemplateId: 0,
    rubricTemplateSaveOption: 'do not save as template',
    rubricTemplate: {},
    rubricTemplateOptions: [],
    templates: [],
    modalTitle: '',
    rubricItems: [],
    name: '',
    description: '',
    currentOrderedRubricItems: [],
    savedRubricItems: null,
    savedRubricShown: true
  }),
  async mounted () {
    if (!this.isTemplate && this.canUseTemplates) {
      await this.getRubricTemplates()
    }
    if (this.isEdit || this.rubricInfo.rubric) {
      console.error(this.rubricInfo.rubric)
      if (!this.isTemplate && this.assignmentId && this.questionId) {
        await this.getRubricPointsBreakdowns()
      }
      this.name = this.rubricInfo.name
      this.description = this.rubricInfo.description
      let rubricInfo
      try {
        rubricInfo = JSON.parse(this.rubricInfo.rubric)
        console.error(rubricInfo)
      } catch (error) {
        console.error('Error creating rubricInfo')
        console.error(this.rubricInfo)
        console.error(error)
      }
      const rubricItems = rubricInfo.rubric_items
      this.rubricShown = rubricInfo.rubric_shown
      // dynamic rubrics keep each item's id so students' applied items stay linked to it
      this.scoringStyle = rubricInfo.scoring_style === 'dynamic' ? 'dynamic' : 'static'
      this.initialScoringStyle = this.scoringStyle
      this.rubricItems = rubricItems
      this.$emit('setKeyValue', 'rubricShown', this.rubricShown)
      this.$emit('setKeyValue', 'rubricTemplateSaveOption', this.rubricTemplateSaveOption)
      this.$emit('setKeyValue', 'rubricVersion', this.scoringStyle === 'dynamic' ? (+rubricInfo.version || 0) : 0)
      this.scalePoints()
      // what's saved, so a conversion can start from it
      this.savedRubricItems = JSON.parse(JSON.stringify(this.rubricItems))
      this.savedRubricShown = this.rubricShown
    }
    // a grader who can't choose the style adds rubrics in the style the instructor set for them
    if (!this.isEdit && !this.rubricInfo.rubric && !this.canChangeScoringStyle) {
      this.scoringStyle = (this.rubricPermissions || {}).default_scoring_style === 'dynamic' ? 'dynamic' : 'static'
    }
    this.$emit('setKeyValue', 'rubricScoringStyle', this.scoringStyle)
  },
  methods: {
    restoreSavedRubricForConversion () {
      // the conversion uses the saved rubric, so undo any unsaved edits to it
      this.rubricTemplateSaveOption = 'do not save as template'
      if (!this.savedRubricItems) {
        return
      }
      const plain = items => JSON.stringify(items.map(item => [item.title, item.description, +item.points]))
      const changed = plain(this.rubricItems) !== plain(this.savedRubricItems) || this.rubricShown !== this.savedRubricShown
      this.rubricItems = JSON.parse(JSON.stringify(this.savedRubricItems))
      this.rubricShown = this.savedRubricShown
      if (changed) {
        this.$noty.info('Your unsaved changes to the criteria were undone: the rubric is converted as it was saved. You can edit it after converting.')
      }
    },
    rubricItemKey (rubricItem) {
      if (!rubricItemKeys.has(rubricItem)) {
        rubricItemKeys.set(rubricItem, `new-${nextRubricItemKey++}`)
      }
      return rubricItemKeys.get(rubricItem)
    },
    getRunningTotal () {
      this.runningTotal = this.rubricItems.reduce((sum, item) => sum + +item.points, 0)
      return this.runningTotal
    },
    roundedRunningTotal () {
      return Math.round(this.getRunningTotal() * 100) / 100
    },
    totalMatchesQuestionPoints () {
      return Math.abs(this.getRunningTotal() - this.questionPoints) < 0.005
    },
    scaleItemsToQuestionPoints () {
      // dynamic only: a one-off rescale the instructor asks for; saving then previews whose scores change
      const total = this.getRunningTotal()
      if (!total || !this.questionPoints) {
        this.$noty.info('Please give at least one criterion some points first.')
        return
      }
      for (let i = 0; i < this.rubricItems.length; i++) {
        this.rubricItems[i].points = Math.round((+this.rubricItems[i].points / total) * this.questionPoints * 100) / 100
      }
      this.rubricItems = [...this.rubricItems]
    },
    async getRubricPointsBreakdowns () {
      try {
        const { data } = await axios.get(`/api/rubric-points-breakdown/assignment/${this.assignmentId}/question/${this.questionId}/exists`)
        if (data.type === 'error') {
          this.$noty.error(data.message)
          return false
        }
        this.rubricPointsBreakdownExists = data.rubric_points_breakdown_exists
      } catch (error) {
        this.$noty.error(error.message)
      }
    },
    deleteRubricCriterion () {
      this.rubricItems = this.rubricItems.filter(item => item !== this.rubricItemToDelete)
      this.scalePoints()
      this.$bvModal.hide('modal-confirm-delete-rubric-criterion')
      this.$noty.info('The criterion has been removed.')
    },
    confirmDeleteCriterion (rubricItem) {
      this.rubricItemToDelete = rubricItem
      this.$bvModal.show('modal-confirm-delete-rubric-criterion')
    },
    scalePoints () {
      if (this.scoringStyle === 'dynamic') {
        return // dynamic points are exact and never scaled automatically
      }
      if (this.questionPoints) {
        let totalPoints = 0
        for (let i = 0; i < this.rubricItems.length; i++) {
          totalPoints += +this.rubricItems[i].points
        }
        for (let i = 0; i < this.rubricItems.length; i++) {
          const points = (this.rubricItems[i].points / totalPoints) * this.questionPoints
          this.rubricItems[i].points = +points.toFixed(4)
        }
      }
    },
    getRubricTemplate (rubricTemplateId) {
      if (!rubricTemplateId) {
        this.rubricItems = []
        this.name = ''
        this.description = ''
        this.rubricTemplateId = 0
        if (this.rubricTemplateSaveOption === 'update existing template') {
          this.rubricTemplateSaveOption = 'do not save as template'
        }
        return
      }
      const template = this.rubricTemplateOptions.find(item => item.value === rubricTemplateId)

      const rubricInfo = JSON.parse(template.rubric) || {}
      const templateScoringStyle = rubricInfo.scoring_style === 'dynamic' ? 'dynamic' : 'static'
      if ((templateScoringStyle === 'dynamic' && this.staticScoresExist) ||
        (templateScoringStyle === 'static' && this.dynamicScoresExist)) {
        const otherStyle = templateScoringStyle === 'dynamic' ? 'static' : 'dynamic'
        this.$noty.error(`That template uses ${templateScoringStyle} scoring, but students have already been graded with this rubric's ${otherStyle} scoring, and loading it would replace their criteria. To change the scoring style, choose it under Scoring Style: you'll see a conversion preview first.`)
        this.rubricTemplate = null
        return
      }
      this.scoringStyle = templateScoringStyle
      if (rubricInfo.hasOwnProperty('rubric_items')) {
        // a template always creates new criteria (no ids)
        this.rubricItems = rubricInfo.rubric_items.map(item => ({
          title: item.title,
          description: item.description,
          points: item.points
        }))
      }
      this.name = template.name
      this.description = template.description
      this.rubricTemplateId = rubricTemplateId
      this.scalePoints()
    },
    async getRubricTemplates () {
      try {
        const { data } = await axios.get('/api/rubric-templates')
        if (data.rubric_templates) {
          this.rubricTemplate = null
          this.rubricTemplateOptions = [{ text: 'Optionally choose a template', value: null, rubric: null }]
          for (let i = 0; i < data.rubric_templates.length; i++) {
            const template = data.rubric_templates[i]
            this.rubricTemplateOptions.push({
              text: template.name,
              value: template.id,
              rubric: template.rubric,
              name: template.name,
              description: template.description
            })
          }
        }
      } catch (error) {
        this.$noty.error(error.message)
      }
    },
    addRubricItemRow () {
      this.rubricItems.push({ title: '', description: '', points: '' })
    }
  }
}
</script>

<style scoped>

</style>
