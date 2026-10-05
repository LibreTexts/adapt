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
    <b-form-group
      v-if="rubricTemplateOptions.length"
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
                         @change="getRubricTemplate($event)"
          />
        </b-col>
      </b-form-row>
    </b-form-group>
    <b-form-group
      v-show="!isTemplate"
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
          <th scope="col" style="width:15px"/>
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
        <tbody>
        <tr
          v-for="( rubricItem,rubricItemIndex) in rubricItems"
          :key="`rubric-criterion-${rubricItemIndex}`"
        >
          <td>
            <b-icon icon="trash" @click="confirmDeleteCriterion(rubricItem)"/>
          </td>
          <td>
            <b-form-input
              v-model="rubricItem.title"
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
              type="text"
              placeholder=""
              rows="2"
              required
              size="sm"
              style="width:100%"
            />
          </td>
          <td>
            <b-form-input
              v-model="rubricItem.points"
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
        <tr>
          <td/>
          <td/>
          <td/>
          <td class="font-weight-bold">
            Total: <span>{{ getRunningTotal() }}</span> <span v-show="assignmentId"><QuestionCircleTooltip
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
        </tbody>
      </table>
    </div>
    <p>
      <b-button variant="info" class="mr-1"
                size="sm" @click="addRubricItemRow"
      >
        Add Criterion
      </b-button>
    </p>
    <b-alert variant="danger" :show="rubricPointsBreakdownExists">
      You have already saved the point breakdowns for at least one student. If you update this rubric, all rubric point
      breakdowns
      will be reset and you will
      have to enter new scores for your students.
    </b-alert>
  </div>
</template>

<script>
import axios from 'axios'
import ErrorMessage from './ErrorMessage.vue'

export default {
  name: 'RubricProperties',
  components: {
    ErrorMessage
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
    }
  },
  data: () => ({
    rubricShown: true,
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
    currentOrderedRubricItems: []
  }),
  async mounted () {
    if (!this.isTemplate) {
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
      this.rubricItems = rubricItems
      this.$emit('setKeyValue', 'rubricShown', this.rubricShown)
      this.$emit('setKeyValue', 'rubricTemplateSaveOption', this.rubricTemplateSaveOption)
      this.scalePoints()
    }
  },
  methods: {
    getRunningTotal () {
      this.runningTotal = this.rubricItems.reduce((sum, item) => sum + +item.points, 0)
      return this.runningTotal
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
      this.rubricItems = this.rubricItems.filter(item => item.title !== this.rubricItemToDelete.title)
      this.scalePoints()
      this.$bvModal.hide('modal-confirm-delete-rubric-criterion')
      this.$noty.info('The criterion has been removed.')
    },
    confirmDeleteCriterion (rubricItem) {
      this.rubricItemToDelete = rubricItem
      this.$bvModal.show('modal-confirm-delete-rubric-criterion')
    },
    scalePoints () {
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
      if (rubricInfo.hasOwnProperty('rubric_items')) {
        this.rubricItems = rubricInfo.rubric_items
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
