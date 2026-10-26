<template>
  <div class="dynamic-rubric-panel">
    <div v-if="!loaded" class="small text-muted">
      <b-spinner small type="grow" />
      Loading rubric...
    </div>
    <div v-else>
      <table class="table table-sm small mb-1" aria-label="Dynamic rubric criteria">
        <tbody>
          <tr v-for="(rubricItem, rubricItemIndex) in rubricItems"
              :key="`dynamic-rubric-item-${rubricItem.id}`"
              :class="{ 'table-success': isApplied(rubricItem.id) }"
              style="cursor:pointer"
              @click="toggle(rubricItem.id)"
          >
            <td style="width:30px">
              <b-badge v-if="rubricItemIndex < 9" variant="light" :title="`Press ${rubricItemIndex + 1}`">
                {{ rubricItemIndex + 1 }}
              </b-badge>
            </td>
            <td style="width:30px">
              <b-form-checkbox :checked="isApplied(rubricItem.id)"
                               :aria-label="`Apply ${rubricItem.title}`"
                               @click.native.stop
                               @change="toggle(rubricItem.id)"
              />
            </td>
            <td>
              {{ rubricItem.title }}
              <QuestionCircleTooltip v-if="rubricItem.description"
                                     :id="`dynamic-rubric-item-tooltip-${rubricItem.id}`"
              />
              <b-tooltip v-if="rubricItem.description"
                         :target="`dynamic-rubric-item-tooltip-${rubricItem.id}`"
                         delay="250"
                         triggers="hover focus"
              >
                {{ rubricItem.description }}
              </b-tooltip>
            </td>
            <td class="text-right" style="width:60px">
              +{{ formatNumber(rubricItem.points) }}
            </td>
          </tr>
        </tbody>
      </table>
      <b-form-row class="align-items-center mb-1">
        <b-col cols="auto" class="small">
          <label :for="`dynamic-rubric-adjustment-${userId}`" class="mb-0">Adjustment (+/-)</label>
        </b-col>
        <b-col cols="auto">
          <b-form-input :id="`dynamic-rubric-adjustment-${userId}`"
                        v-model="adjustmentInput"
                        type="number"
                        step="0.5"
                        size="sm"
                        style="width:90px"
                        @input="adjustmentChanged"
          />
        </b-col>
        <b-col cols="auto">
          <b-button size="sm"
                    variant="outline-secondary"
                    class="text-nowrap"
                    title="Clears every criterion and the adjustment (key 0). Saved when you move to another student."
                    @click="noCredit()"
          >
            Clear All
          </b-button>
        </b-col>
      </b-form-row>
      <div class="small font-weight-bold">
        Rubric score: {{ formatNumber(score.final) }} / {{ formatNumber(cap) }}
        <span v-if="score.raw > cap" class="font-weight-normal text-muted">
          (criteria add up to {{ formatNumber(score.raw) }}; capped at {{ formatNumber(cap) }})
        </span>
        <span v-if="latePenalty > 0" class="font-weight-normal text-muted">
          (after the {{ latePenalty }}% late penalty)
        </span>
      </div>
      <div class="small text-muted">
        Keys 1-9 apply criteria, 0 clears all. Changes are saved when you move to another student.
      </div>
    </div>
  </div>
</template>

<script>
import axios from 'axios'

export default {
  name: 'DynamicRubricGradingPanel',
  props: {
    rubric: {
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
    userId: {
      type: Number,
      default: 0
    },
    questionPoints: {
      type: Number,
      default: 0
    },
    autoGradedScore: {
      type: [Number, String],
      default: 0
    },
    latePenaltyPercent: {
      type: [Number, String],
      default: null
    }
  },
  data: () => ({
    loaded: false,
    appliedItemIds: [],
    adjustmentInput: 0
  }),
  computed: {
    parsedRubric () {
      try {
        return JSON.parse(this.rubric) || {}
      } catch (error) {
        return {}
      }
    },
    rubricItems () {
      return this.parsedRubric.rubric_items || []
    },
    rubricVersion () {
      return +this.parsedRubric.version || 0
    },
    adjustment () {
      const adjustment = Number(this.adjustmentInput)
      return isNaN(adjustment) ? 0 : Math.round(adjustment * 100) / 100
    },
    latePenalty () {
      const latePenalty = Number(this.latePenaltyPercent)
      return isNaN(latePenalty) ? 0 : Math.min(Math.max(Math.round(latePenalty), 0), 100)
    },
    cap () {
      // same limit the server uses: the question's points minus the auto-graded score
      return Math.max(0, +this.questionPoints - (+this.autoGradedScore || 0))
    },
    score () {
      // a preview only; the server always recalculates the score when it saves
      let raw = this.adjustment
      for (const item of this.rubricItems) {
        if (this.appliedItemIds.includes(String(item.id))) {
          raw += +item.points
        }
      }
      const clamped = Math.min(Math.max(raw, 0), this.cap)
      const final = Math.round(clamped * (1 - this.latePenalty / 100) * 100) / 100
      return { raw: Math.round(raw * 100) / 100, clamped, final }
    }
  },
  watch: {
    autoGradedScore () {
      this.emitChange(false)
    },
    latePenaltyPercent () {
      this.emitChange(false)
    }
  },
  async mounted () {
    window.addEventListener('keydown', this.keyListener)
    await this.getDynamicRubricState()
  },
  beforeDestroy () {
    window.removeEventListener('keydown', this.keyListener)
  },
  methods: {
    formatNumber (number) {
      return Math.round(+number * 100) / 100
    },
    isApplied (itemId) {
      return this.appliedItemIds.includes(String(itemId))
    },
    toggle (itemId) {
      itemId = String(itemId)
      this.appliedItemIds = this.isApplied(itemId)
        ? this.appliedItemIds.filter(appliedItemId => appliedItemId !== itemId)
        : [...this.appliedItemIds, itemId]
      this.emitChange(true)
    },
    adjustmentChanged () {
      this.emitChange(true)
    },
    noCredit () {
      this.appliedItemIds = []
      this.adjustmentInput = 0
      this.emitChange(true)
    },
    emitChange (byGrader) {
      if (!this.loaded) {
        return
      }
      this.$emit('change', {
        applied_item_ids: [...this.appliedItemIds],
        adjustment: this.adjustment,
        rubric_version: this.rubricVersion,
        score: this.score.final,
        by_grader: byGrader
      })
    },
    keyListener (event) {
      if (!this.loaded || event.ctrlKey || event.metaKey || event.altKey) {
        return
      }
      // ignore while typing or when a dialog is open; a checkbox or radio that was just clicked isn't typing
      const active = document.activeElement
      const clickedChoice = active && active.tagName === 'INPUT' && ['checkbox', 'radio'].includes(active.type)
      const typing = (active && !clickedChoice && (active.id === 'text_comments' ||
          ['INPUT', 'TEXTAREA', 'SELECT'].includes(active.tagName) ||
          active.isContentEditable)) ||
        Boolean(document.querySelector('.modal.show'))
      if (typing || !/^[0-9]$/.test(event.key)) {
        return
      }
      event.preventDefault()
      if (event.key === '0') {
        this.noCredit()
        return
      }
      const rubricItem = this.rubricItems[+event.key - 1]
      if (rubricItem) {
        this.toggle(rubricItem.id)
      }
    },
    async getDynamicRubricState () {
      try {
        const { data } = await axios.get(`/api/rubric-points-breakdown/assignment/${this.assignmentId}/question/${this.questionId}/user/${this.userId}`)
        if (data.type !== 'success') {
          this.$noty.error(data.message)
          return
        }
        const state = data.dynamic_rubric_state || {}
        // keep only items that are still in the rubric
        const itemIds = this.rubricItems.map(item => String(item.id))
        this.appliedItemIds = (state.applied_item_ids || []).map(String).filter(itemId => itemIds.includes(itemId))
        this.adjustmentInput = +state.adjustment || 0
        this.loaded = true
        this.$emit('loaded', {
          exists: Boolean(state.exists),
          applied_item_ids: [...this.appliedItemIds],
          adjustment: this.adjustment,
          rubric_version: this.rubricVersion,
          score: this.score.final
        })
      } catch (error) {
        this.$noty.error(error.message)
      }
    }
  }
}
</script>
