<template>
  <div>
    <p>Choose one of the question types below to further narrow your search.</p>
    <div v-show="technology === 'h5p'">
      <b-form-group
        id="h5p-type-to-view"
      >
        <b-form-radio-group
          v-model="h5pTypeToView"
          stacked
          required
          @input="setH5pTypeToView($event)"
        >
          <b-form-radio name="h5p-type-to-view" value="auto-graded">
            Auto-graded
            <QuestionCircleTooltip id="auto-graded"/>
            <b-tooltip target="auto-graded"
                       delay="250"
                       triggers="hover focus"
            >
              Students respond to questions and get feedback on submissions
            </b-tooltip>
          </b-form-radio>
          <b-form-radio name="h5p-type-to-view" value="interactive">
            Interactive
            <QuestionCircleTooltip id="interactive"/>
            <b-tooltip target="interactive"
                       delay="250"
                       triggers="hover focus"
            >
              There is nothing for students to submit. These questions are more appropriate for open-ended type
              assessments.
            </b-tooltip>
          </b-form-radio>
        </b-form-radio-group>
      </b-form-group>
      <hr>
    </div>
    <div class="radio-grid">
      <div v-for="(option, index) in formattedQuestionTypesToView"
           :key="index"
           class="radio-item"
           :style="`width:${width}`"
      >
        <b-form-radio :id="`${formattedQuestionType}-${index}`"
                      v-model="formattedQuestionType"
                      :value="option.formatted_question_type"
                      @change="setFormattedQuestionType(option.formatted_question_type)"
        >
          {{ displayFormattedQuestionType(option.formatted_question_type) }}
        </b-form-radio>
      </div>
    </div>
  </div>
</template>

<script>
export default {
  name: 'FormattedQuestionTypeRadio',
  props: {
    technology: {
      type: String,
      default: ''
    },
    formattedQuestionTypesOptionsByTechnology: {
      type: Array,
      default: () => {
      }
    },
    interactiveFormattedTypes: {
      type: Array,
      default: () => {
      }
    },
    qtiContentType: {
      type: String,
      default: ''
    }
  },
  data: () => ({
    h5pTypeToView: 'auto-graded',
    formattedQuestionTypesToView: [],
    formattedQuestionType: '',
    width: ''

  }),
  mounted () {
    this.formattedQuestionTypesToView = this.formattedQuestionTypesOptionsByTechnology
    if (this.technology === 'h5p') {
      this.formattedQuestionTypesToView = this.formattedQuestionTypesToView.filter(item => !this.interactiveFormattedTypes.includes(item.formatted_question_type))
    }
    switch (this.technology) {
      case ('webwork'):
      case ('imathas'):
      case ('qti'):
        this.width = '100%'
        break
      case ('h5p'):
      case ('any'):
        this.width = '25%'
        break
    }
  },
  methods: {
    displayFormattedQuestionType (formattedQuestionType) {
      // Mirrors the same rule in QuestionsGet.vue: show the full 'Accounting ...'
      // label when 'All' is selected (types from every bucket are mixed together),
      // trim the redundant 'Accounting ' prefix when a specific bucket is already
      // selected. The underlying value/v-model is untouched either way.
      if (this.qtiContentType === 'all') {
        return formattedQuestionType
      }
      const accountingDisplayLabels = {
        'Accounting Journal Entry': 'Journal Entry',
        'Accounting Report': 'Report',
        'Accounting Multi Part Computation': 'Multi-part Computation'
      }
      return accountingDisplayLabels[formattedQuestionType] || formattedQuestionType
    },
    setH5pTypeToView (h5pTypeToView) {
      this.formattedQuestionTypesToView = this.formattedQuestionTypesOptionsByTechnology
      if (h5pTypeToView === 'auto-graded') {
        this.formattedQuestionTypesToView = this.formattedQuestionTypesToView.filter(item => !this.interactiveFormattedTypes.includes(item.formatted_question_type))
      } else {
        this.formattedQuestionTypesToView = this.formattedQuestionTypesToView.filter(item => this.interactiveFormattedTypes.includes(item.formatted_question_type))
      }
    },
    setFormattedQuestionType (formattedQuestionType) {

      this.$emit('setFormattedQuestionType', formattedQuestionType)
    }
  }
}
</script>

<style scoped>
.radio-grid {
  display: flex;
  flex-wrap: wrap;
}
</style>
