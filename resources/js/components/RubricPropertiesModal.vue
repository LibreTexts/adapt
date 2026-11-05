<template>
<span>
<AllFormErrors :all-form-errors="allFormErrors" :modal-id="formErrorsModalId"/>
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
                    :rubric-permissions="rubricPermissions"
                    :errors="errors"
                    @setRubricItems="setRubricItems"
                    @hideRubricProperties="showRubricProperties = false"
                    @setRunningTotal="setRunningTotal"
                    @setKeyValue="setKeyValue"
  />
  <b-alert :show="dynamicErrors.length > 0" variant="danger" class="mt-2">
    <div v-for="(dynamicError, index) in dynamicErrors" :key="`dynamic-rubric-error-${index}`">
      {{ dynamicError }}
    </div>
  </b-alert>
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
  <DynamicRubricImpactModal :modal-id="impactModalId"
                            :preview="dynamicPreview"
                            :processing="applyingDynamicRubric"
                            @apply="applyDynamicRubric"
  />
  <DynamicRubricConversionModal :modal-id="conversionModalId"
                                :preview="conversionPreview"
                                :processing="applyingDynamicRubric"
                                @apply="applyConversion"
  />
</span>
</template>

<script>
import axios from 'axios'
import { fixInvalid } from '~/helpers/accessibility/FixInvalid'
import AllFormErrors from '~/components/AllFormErrors'
import RubricProperties from './RubricProperties.vue'
import DynamicRubricImpactModal from './DynamicRubricImpactModal.vue'
import DynamicRubricConversionModal from './DynamicRubricConversionModal.vue'

export default {
  name: 'RubricPropertiesModal',
  components: { AllFormErrors, RubricProperties, DynamicRubricImpactModal, DynamicRubricConversionModal },
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
    },
    rubricPermissions: {
      type: Object,
      default: null
    }
  },
  data: () => ({
    errors: {},
    allFormErrors: [],
    formErrorsModalId: 'modal-form-errors-rubric-properties',
    rubricItems: [],
    updatedModalTitle: '',
    runningTotal: 0,
    description: '',
    name: '',
    scoreInputType: '',
    rubricShown: true,
    rubricTemplate: null,
    rubricTemplateSaveOption: 'do not save as template',
    rubricScoringStyle: 'static',
    rubricVersion: 0,
    dynamicErrors: [],
    dynamicPreview: null,
    applyingDynamicRubric: false,
    impactModalId: 'modal-dynamic-rubric-impact',
    // static rubric with static scores switched to dynamic
    rubricConverting: false,
    rubricConversionDirection: '',
    conversionPreview: null,
    conversionFingerprint: '',
    conversionModalId: 'modal-dynamic-rubric-conversion'
  }),
  mounted () {
    if (this.isTemplate) {
      const action = this.isEdit ? 'Edit' : 'Create'
      this.updatedModalTitle = `${action} Rubric Template`
    } else if (this.modalTitle) {
      this.updatedModalTitle = this.modalTitle
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
      // a dynamic rubric in an assignment is saved through preview -> apply, so graded students are rescored
      const isAssignmentDynamicRubric = !this.isTemplate && Boolean(this.assignmentId) && this.rubricScoringStyle === 'dynamic'
      this.dynamicErrors = []
      this.errors = {}
      if (this.rubricConverting && !this.isTemplate && this.assignmentId) {
        await this.previewConversion()
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
            rubric_shown: this.rubricShown,
            scoring_style: this.rubricScoringStyle
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
          if (data.type === 'success' && isAssignmentDynamicRubric) {
            // the template is saved; now save the assignment's rubric the dynamic way
            await this.previewDynamicRubric()
            return
          }
          if (data.type === 'success') {
            this.$bvModal.hide('modal-rubric-properties')
            saveAsTemplate
              ? this.$emit('setCustomRubric', JSON.stringify({
                rubric_items: this.staticRubricItems(),
                rubric_shown: this.rubricShown
              }))
              : this.$emit('reloadRubricTemplates')
            if (this.assignmentId) {
              this.$emit('reloadRubricAndRubricPointsBreakdown')
            }
          }
        } else if (isAssignmentDynamicRubric) {
          await this.previewDynamicRubric()
        } else {
          const { data } = await axios.post(`/api/rubric-templates/validate-rubric-items`,
            {
              save_as_template: false,
              rubric_items: this.rubricItems,
              rubric_shown: this.rubricShown,
              scoring_style: this.rubricScoringStyle
            })
          if (data.type === 'success') {
            this.$emit('setCustomRubric', JSON.stringify({
              rubric_items: this.staticRubricItems(),
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
          this.showFormErrors(this.formErrorMessages(this.errors))
        }
      }
    },
    // the site's pattern: a modal listing every error, and each error under its field
    showFormErrors (messages) {
      if (!messages.length) {
        return
      }
      this.allFormErrors = messages
      this.$bvModal.show(this.formErrorsModalId)
      this.$nextTick(() => {
        fixInvalid()
      })
    },
    formErrorMessages (errors) {
      const messages = []
      Object.keys(errors || {}).forEach(key => {
        if (key === 'rubric_items') {
          Object.keys(errors.rubric_items || {}).forEach(index => {
            const itemErrors = errors.rubric_items[index] || {}
            Object.keys(itemErrors).forEach(field => {
              if (itemErrors[field] && itemErrors[field] !== 'passes') {
                messages.push(`Criterion ${+index + 1}: ${itemErrors[field]}`)
              }
            })
          })
        } else {
          const value = errors[key]
          const message = Array.isArray(value) ? value[0] : value
          if (message) {
            messages.push(message)
          }
        }
      })
      return messages
    },
    showDynamicErrors (data) {
      if (data.item_errors) {
        // field errors go under their fields and in the errors modal; anything else stays in the alert
        this.errors = { ...this.errors, rubric_items: data.item_errors }
        this.dynamicErrors = data.general_errors || []
        this.showFormErrors(this.formErrorMessages({ rubric_items: data.item_errors }).concat(this.dynamicErrors))
        return
      }
      this.dynamicErrors = data.errors && data.errors.length ? data.errors : [data.message]
    },
    staticRubricItems () {
      // static rubrics don't keep the item ids a dynamic rubric uses
      return this.rubricItems.map(item => ({
        title: item.title,
        description: item.description,
        points: item.points
      }))
    },
    dynamicRubricPayload () {
      return {
        rubric_items: this.rubricItems.map(item => ({
          id: item.id,
          title: item.title,
          description: item.description,
          points: item.points
        })),
        rubric_shown: this.rubricShown,
        base_version: this.rubricVersion
      }
    },
    async previewDynamicRubric () {
      this.dynamicErrors = []
      try {
        const { data } = await axios.post(`/api/assignments/${this.assignmentId}/questions/${this.questionId}/dynamic-rubric/preview`,
          this.dynamicRubricPayload())
        if (data.type !== 'success') {
          this.showDynamicErrors(data)
          return
        }
        this.dynamicPreview = data.preview
        if (this.nothingToReview(data.preview)) {
          // no score changes and no applied criterion removed: save straight away
          await this.applyDynamicRubric(false)
          return
        }
        this.$bvModal.show(this.impactModalId)
      } catch (error) {
        this.$noty.error(error.message)
      }
    },
    nothingToReview (preview) {
      return !preview.students_changed.length &&
        !preview.deleted_items.some(deletedItem => +deletedItem.students > 0)
    },
    conversionUrl (suffix) {
      // to_dynamic: static -> dynamic (Stage 6); to_static: dynamic -> static
      const action = this.rubricConversionDirection === 'to_static' ? 'convert-to-static' : 'convert'
      return `/api/assignments/${this.assignmentId}/questions/${this.questionId}/dynamic-rubric/${action}${suffix}`
    },
    async previewConversion () {
      try {
        const { data } = await axios.post(this.conversionUrl('-preview'))
        if (data.type !== 'success') {
          this.dynamicErrors = [data.message]
          return
        }
        this.conversionPreview = data.preview
        this.conversionFingerprint = data.fingerprint
        this.$bvModal.show(this.conversionModalId)
      } catch (error) {
        this.$noty.error(error.message)
      }
    },
    async applyConversion ({ choice, emailStudents }) {
      this.applyingDynamicRubric = true
      try {
        const { data } = await axios.patch(this.conversionUrl(''),
          { choice, email_students: emailStudents, fingerprint: this.conversionFingerprint })
        this.$bvModal.hide(this.conversionModalId)
        if (data.type !== 'success') {
          this.dynamicErrors = [data.message]
          return
        }
        this.$noty.success(data.message)
        this.$bvModal.hide('modal-rubric-properties')
        // the version tells this page's live-update listener the change was its own
        this.$emit('dynamicRubricSaved', data.rubric, data.published_version || data.rubric.version)
      } catch (error) {
        this.$noty.error(error.message)
      } finally {
        this.applyingDynamicRubric = false
      }
    },
    async applyDynamicRubric (emailStudents) {
      this.applyingDynamicRubric = true
      try {
        const { data } = await axios.patch(`/api/assignments/${this.assignmentId}/questions/${this.questionId}/dynamic-rubric`,
          { ...this.dynamicRubricPayload(), email_students: emailStudents })
        if (data.type === 'error') {
          this.$bvModal.hide(this.impactModalId)
          this.showDynamicErrors(data)
          return
        }
        this.$noty[data.type](data.message)
        this.$bvModal.hide(this.impactModalId)
        this.$bvModal.hide('modal-rubric-properties')
        if (data.type === 'success') {
          this.$emit('dynamicRubricSaved', data.rubric)
        }
      } catch (error) {
        this.$noty.error(error.message)
      } finally {
        this.applyingDynamicRubric = false
      }
    }
  }
}
</script>
