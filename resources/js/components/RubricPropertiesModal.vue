<template>
<span>
<b-modal id="modal-rubric-properties"
         :title="updatedModalTitle"
         size="xl"
         no-close-on-backdrop
         @hidden="$emit('hideRubricProperties')"
>
  <RubricProperties :key="`rubric-properties-modal-${+showRubricProperties}`"
                    :rubric-info="rubricInfo"
                    :is-template="isTemplate"
                    :is-edit="isEdit"
                    :show-rubric-properties="showRubricProperties"
                    :question-points="questionPoints"
                    :assignment-id="assignmentId"
                    :question-id="questionId"
                    :errors="errors"
                    @setRubricItems="setRubricItems"
                    @hideRubricProperties="showRubricProperties = false"
                    @setRunningTotal="setRunningTotal"
                    @setKeyValue="setKeyValue"
  />
   <template #modal-footer="{ cancel, ok }">
        <b-button size="sm" @click="$bvModal.hide('modal-rubric-properties')">
          Cancel
        </b-button>
        <b-button size="sm"
                  variant="primary"
                  :disabled="!rubricItems.length"
                  @click="handleSubmitRubricInfo()"
        >
          Save
        </b-button>
      </template>
    </b-modal>
</span>
</template>

<script>
import axios from 'axios'
import RubricProperties from './RubricProperties.vue'

export default {
  name: 'RubricPropertiesModal',
  components: { RubricProperties },
  props: {
    modalTitle: {
      type: String,
      default: ''
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
    showRubricProperties: {
      type: Boolean,
      default: false
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
  data: () => ({
    errors: {},
    rubricItems: [],
    updatedModalTitle: '',
    runningTotal: 0,
    description: '',
    name: '',
    scoreInputType: '',
    rubricShown: true,
    rubricTemplate: null,
    rubricTemplateSaveOption: 'do not save as template'
  }),
  mounted () {
    if (this.isTemplate) {
      const action = this.isEdit ? 'Edit' : 'Create'
      this.updatedModalTitle = `${action} Rubric Template`
    } else {
      this.updatedModalTitle = this.assignmentId ? 'Overriding Rubric' : 'Question Rubric'
    }
    this.showRubricProperties ? this.$bvModal.show('modal-rubric-properties') : this.$bvModal.hide('modal-rubric-properties')
  },
  methods: {
    setKeyValue (key, value) {
      this[key] = value
    },
    setRubricItems () {
      alert('To do setRubricItems')
    },
    setRunningTotal () {
      alert('To do setRunningTotal')
    },
    async handleSubmitRubricInfo () {
      const runningTotal = this.runningTotal
      if (this.scoreInputType === 'percentage' && runningTotal !== 100) {
        this.$noty.error(`The total of your percentages should sum to 100%; they currently only sum to ${runningTotal}%.`)
        return
      }
      try {
        const saveAsTemplate = ['save as new template', 'update existing template'].includes(this.rubricTemplateSaveOption)
        if (this.isTemplate || saveAsTemplate) {
          const rubricData = {
            name: this.name,
            description: this.description,
            rubric_items: this.rubricItems,
            save_as_template: true,
            assignment_id: this.assignmentId,
            question_id: this.questionId,
            rubric_shown: this.rubricShown
          }
          // On the template page, isEdit means "editing this template".
          // On a question page, isEdit only means the question already has a rubric,
          // so the "On save" choice alone decides whether a template is updated.
          const updatingExistingTemplate = this.isTemplate
            ? this.isEdit
            : this.rubricTemplateSaveOption === 'update existing template'
          const rubricTemplateId = this.isTemplate ? this.rubricInfo.id : this.rubricTemplate
          if (updatingExistingTemplate && !rubricTemplateId) {
            this.$noty.error('Please choose the template you would like to update.')
            return
          }
          const action = updatingExistingTemplate ? 'patch' : 'post'
          const url = updatingExistingTemplate
            ? `/api/rubric-templates/${rubricTemplateId}`
            : '/api/rubric-templates'
          const { data } = await axios[action](url, rubricData)
          this.$noty[data.type](data.message)
          if (data.type === 'success') {
            this.$bvModal.hide('modal-rubric-properties')
            saveAsTemplate
              ? this.$emit('setCustomRubric', JSON.stringify({
                rubric_items: this.rubricItems,
                rubric_shown: this.rubricShown
              }))
              : this.$emit('reloadRubricTemplates')
            if (this.assignmentId) {
              this.$emit('reloadRubricAndRubricPointsBreakdown')
            }
          }
        } else {
          const { data } = await axios.post(`/api/rubric-templates/validate-rubric-items`,
            {
              save_as_template: false,
              rubric_items: this.rubricItems,
              rubric_shown: this.rubricShown
            })
          if (data.type === 'success') {
            this.$emit('setCustomRubric', JSON.stringify({
              rubric_items: this.rubricItems,
              rubric_shown: this.rubricShown
            }))
          }
          if (this.assignmentId) {
            this.$emit('reloadRubricAndRubricPointsBreakdown')
          }
        }
      } catch (error) {
        if (!error.message.includes('status code 422')) {
          this.$noty.error(error.message)
        } else {
          this.errors = error.response.data.errors
          if (this.errors.rubric_items) {
            this.errors.rubric_items = JSON.parse(this.errors.rubric_items)
          }
        }
      }
    }
  }
}
</script>
