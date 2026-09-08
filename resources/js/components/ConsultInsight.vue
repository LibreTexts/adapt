<template>
  <span class="tooltip-wrapper">
    <a
      :id="tooltipId"
      ref="link"
      :href="url"
      :class="[formattingClass, 'info-link']"
      target="_blank"
      aria-label="Consult Insight"
      @click="$refs.link.blur()"
    >
      <font-awesome-icon :icon="infoIcon"/>
    </a>

    <b-tooltip
      :target="tooltipId"
      delay="250"
      triggers="hover focus"
    >
      Consult Insight
    </b-tooltip>
  </span>
</template>

<script>
import { faInfoCircle } from '@fortawesome/free-solid-svg-icons'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'

export default {
  name: 'ConsultInsight',
  components: {
    FontAwesomeIcon
  },
  props: {
    url: {
      type: String,
      default: ''
    },
    formattingClass: {
      type: String,
      default: 'ml-auto mr-1'
    }
  },
  data () {
    return {
      infoIcon: faInfoCircle
    }
  },
  computed: {
    tooltipId () {
      return `consult-insight-tooltip-${this._uid}`
    }
  }
}
</script>
