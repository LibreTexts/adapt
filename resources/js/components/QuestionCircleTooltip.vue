<template>
  <a :id="id"
     href=""
     :class="'text-dark'"
     :aria-label="accessibleLabel"
     @click.prevent
  >
    <b-icon :style="iconStyle" icon="question-circle" aria-hidden="true"/>
  </a>
</template>

<script>
import _ from 'lodash'

export default {
  name: 'QuestionCircleTooltip',
  props: {
    iconStyle: {
      type: String,
      default: ''
    },
    color: {
      type: String,
      default: 'text-muted'
    },
    id: {
      type: String,
      default: ''
    },
    // Optional descriptive label. When omitted, the label is derived from the id (previous behavior).
    ariaLabel: {
      type: String,
      default: ''
    }
  },
  computed: {
    accessibleLabel () {
      if (this.ariaLabel) {
        return this.ariaLabel
      }
      const label = _.upperFirst(this.id.replace('_', ' '))
      return _.replace(label, /_|-/g, ' ')
    }
  }
}
</script>

<style scoped>

</style>
