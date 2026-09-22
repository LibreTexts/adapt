<template>
  <div>
    <b-modal
      id="modal-hint"
      title="Hint"
      size="lg"
      @shown="typesetMath('#hint-html')"
    >
      <template #modal-title>
        <h2 class="editable mb-0">Hint</h2>
      </template>

      <b-alert :show="user.role === 2" variant="info">
        Students receive a {{ hintPenaltyIfShownHint }}% penalty for viewing the hint.
      </b-alert>

      <div id="hint-html" v-html="hintWithoutTitle"/>
      <template #modal-footer="{ ok }">
        <b-button
          size="sm"
          variant="primary"
          @click="$bvModal.hide('modal-hint')"
        >
          OK
        </b-button>
      </template>
    </b-modal>
  </div>
</template>

<script>
import { mapGetters } from 'vuex'

export default {
  name: 'HintModal',

  props: {
    hintPenaltyIfShownHint: {
      type: Number,
      default: 0
    },
    hintHtml: {
      type: String,
      default: ''
    }
  },
  computed: {
    ...mapGetters({
      user: 'auth/user'
    }),
    hintWithoutTitle () {
      return this.hintHtml.replace(
        /<h2[^>]*>\s*Hint\s*<\/h2>/gi,
        ''
      )
    }
  }
}
</script>
