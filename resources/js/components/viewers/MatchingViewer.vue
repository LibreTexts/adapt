<template>
  <div>
    <table id="matching-table" class="table table-striped">
      <thead>
      <tr>
        <th scope="col">
          Term to match
        </th>
        <th scope="col">
          Chosen match
        </th>
      </tr>
      </thead>
      <tbody>
      <tr v-for="(item,index) in termsToMatch" :key="`matching-answer-${item.identifier}`">
        <th scope="row">
          <div v-html="formatQuestionMediaPlayer(item.termToMatch)"/>
        </th>
        <td :class="qtiJson.studentResponse ? 'd-flex' : ''">
          <div>
            <b-dropdown :id="`matching-answer-${item.identifier}`"
                        :ref="`dropdown-${item.identifier}`"
                        :html="getChosenMatch(item)"
                        class="matching-dropdown m-md-2"
                        menu-class="qti-matching-dropdown-menu"
                        no-flip
                        :variant="item.chosenMatchIdentifier === null ? 'secondary' : 'info'"
                        @shown="onDropdownShown(item)"
            >
              <div v-if="item.chosenMatchIdentifier !== null"
                   class="dropdown-match-item dropdown-match-item__clear"
                   @click="clearChosenMatch(item)"
              >
                <b-icon-arrow-counterclockwise class="mr-1"/>Clear selection
              </div>
              <div v-for="possibleMatch in getAvailableMatches(item)"
                   :key="`possible-match-${possibleMatch.identifier}`"
                   class="dropdown-match-item"
              >
                <!-- Unified layout when any item in the list is a media player -->
                <div v-if="hasMediaPlayer" class="dropdown-match-item__media-row">
                  <span v-html="formatQuestionMediaPlayer(possibleMatch.matchingTerm)"
                        class="dropdown-match-item__player"
                  />
                  <button class="btn btn-info btn-block dropdown-match-item__select-btn"
                          @click.stop="updateChosenMatch(item, possibleMatch)"
                  >
                    Select
                  </button>
                </div>

                <!-- Pure text list — direct click -->
                <div v-else
                     class="dropdown-match-item__text"
                     @click="updateChosenMatch(item, possibleMatch)"
                >
                  <span v-html="possibleMatch.matchingTerm"/>
                </div>
              </div>
            </b-dropdown>

            <!-- Render the selected media player inline below the dropdown -->
            <div v-if="chosenMatchHasMediaPlayer(item)"
                 class="chosen-media-player mt-2 ml-2"
                 v-html="formatQuestionMediaPlayer(getChosenMatchTerm(item))"
            />
          </div>

          <div v-if="qtiJson.studentResponse
              && qtiJson.studentResponse[index].chosenMatchIdentifier === item.chosenMatchIdentifier
              && qtiJson.studentResponse[index].hasOwnProperty('answeredCorrectly')"
               class="mt-3 ml-1"
          >
            <b-icon-check-circle-fill v-if="qtiJson.studentResponse[index].answeredCorrectly"
                                      class="text-success mr-2"
                                      scale="1.5"
            />
            <b-icon-x-circle-fill v-if="!qtiJson.studentResponse[index].answeredCorrectly"
                                  class="text-danger mr-2"
                                  scale="1.5"
            />
          </div>
          <input type="hidden" class="form-control is-invalid">
          <div class="help-block invalid-feedback">
            {{ item.errorMessage }}
          </div>
        </td>
      </tr>
      </tbody>
    </table>
    <b-card v-if="termsToMatchWithFeedback.length"
            border-variant="info"
            header="Feedback"
            header-bg-variant="info"
            header-text-variant="white"
            header-class="pt-2 pb-2 pl-3"
    >
      <b-table
        :items="termsToMatchWithFeedback"
        :fields="feedbackFields"
        aria-label="Feedback"
        striped
        hover
        responsive
        head-variant="info"
        :no-border-collapse="true"
      >
        <template v-slot:cell(termToMatch)="data">
          <div v-html="data.item.termToMatch"/>
        </template>
        <template v-slot:cell(feedback)="data">
          <div v-html="data.item.feedback"/>
        </template>
      </b-table>
    </b-card>
  </div>
</template>

<script>
import $ from 'jquery'
import { formatQuestionMediaPlayer } from '../../helpers/Questions'

export default {
  name: 'MatchingViewer',
  props: {
    qtiJson: {
      type: Object,
      default: () => {}
    },
    showResponseFeedback: {
      type: Boolean,
      default: true
    }
  },
  data: () => ({
    feedbackFields: ['termToMatch', 'feedback'],
    showQtiAnswer: false,
    termsToMatch: [],
    possibleMatches: [],
    doNotRepeatErrorMessage: '',
    matchingFeedbacks: ''
  }),
  computed: {
    hasMediaPlayer () {
      const currentDomain = window.location.origin
      return this.possibleMatches.some(match =>
        match.matchingTerm &&
        match.matchingTerm.includes(`${currentDomain}/question-media-player/`)
      )
    },
    termsToMatchWithFeedback () {
      return this.qtiJson.termsToMatch.filter(item => item.feedback && item.feedback !== '')
    },
    nonNullPossibleMatches () {
      return this.possibleMatches.filter(possibleMatch => possibleMatch.identifier !== null)
    }
  },
  mounted () {
    this.termsToMatch = this.qtiJson.termsToMatch
    this.possibleMatches = this.qtiJson.possibleMatches
    this.getMatchingFeedbacks()
    let html
    let chooseMatchMessage = 'Choose a match'
    for (let i = 0; i < this.possibleMatches.length; i++) {
      let possibleMatch = this.possibleMatches[i]
      html = $.parseHTML(possibleMatch.matchingTerm)
      if ($(html).find('img').length) {
        chooseMatchMessage = 'Choose a match from the images below'
        $(html).find('img').each(function () {
          $(this).attr('width', '')
          $(this).attr('height', '')
          possibleMatch.matchingTerm = $(this).prop('outerHTML')
        })
      }
    }
    this.possibleMatches.push({ identifier: null, matchingTerm: chooseMatchMessage })
    for (let i = 0; i < this.termsToMatch.length; i++) {
      if (this.qtiJson.studentResponse) {
        this.termsToMatch[i].chosenMatchIdentifier = this.qtiJson.studentResponse.find(
          response => response.identifier === this.termsToMatch[i].identifier
        ).chosenMatchIdentifier
      } else {
        this.termsToMatch[i].chosenMatchIdentifier = null
        this.termsToMatch[i].errorMessage = ''
      }
    }
    if (this.qtiJson.studentResponse) {
      console.log(this.qtiJson.studentResponse)
    }
  },
  methods: {
    formatQuestionMediaPlayer,
    getAvailableMatches (item) {
      const chosenIdentifiers = this.termsToMatch
        .filter(termToMatch => termToMatch.chosenMatchIdentifier !== null)
        .map(termToMatch => termToMatch.chosenMatchIdentifier)
      return this.nonNullPossibleMatches.filter(possibleMatch => !chosenIdentifiers.includes(possibleMatch.identifier))
    },
    clearChosenMatch (item) {
      item.errorMessage = ''
      item.chosenMatchIdentifier = null
      // Close the dropdown by clicking the toggle button
      this.$nextTick(() => {
        const toggle = document.querySelector(`#matching-answer-${item.identifier} .btn`)
        if (toggle) toggle.click()
      })
      this.$forceUpdate()
    },
    onDropdownShown (item) {
      if (!this.hasMediaPlayer) return
      this.$forceUpdate()
      const menu = document.querySelector('ul.dropdown-menu.show')
      if (menu) {
        menu.style.width = '250px'
        menu.style.minWidth = '250px'
      }
    },
    getMatchingFeedbacks () {
      if (!this.qtiJson?.termsToMatch) return
      for (let i = 0; i < this.qtiJson.termsToMatch.length; i++) {
        let termToMatch = this.qtiJson.termsToMatch[i]
        console.log(termToMatch)
      }
    },
    getChosenMatch (item) {
      const match = this.possibleMatches.find(
        possibleMatch => possibleMatch.identifier === item.chosenMatchIdentifier
      )
      if (!match) return 'Choose a match'
      const currentDomain = window.location.origin
      if (match.matchingTerm.includes(`${currentDomain}/question-media-player/`)) {
        return '&#9654; Audio selected'
      }
      return match.matchingTerm.replace('<p>', '').replace('</p>', '')
    },
    getChosenMatchTerm (item) {
      const match = this.possibleMatches.find(
        possibleMatch => possibleMatch.identifier === item.chosenMatchIdentifier
      )
      return match ? match.matchingTerm : ''
    },
    chosenMatchHasMediaPlayer (item) {
      if (item.chosenMatchIdentifier === null) return false
      const match = this.possibleMatches.find(
        possibleMatch => possibleMatch.identifier === item.chosenMatchIdentifier
      )
      if (!match) return false
      const currentDomain = window.location.origin
      return match.matchingTerm.includes(`${currentDomain}/question-media-player/`)
    },
    updateChosenMatch (item, possibleMatch) {
      item.errorMessage = ''
      item.chosenMatchIdentifier = possibleMatch.identifier
      // Close the dropdown by clicking the toggle button
      this.$nextTick(() => {
        const toggle = document.querySelector(`#matching-answer-${item.identifier} .btn`)
        if (toggle) toggle.click()
        $(`#matching-answer-${item.identifier}`).find('.dropdown-toggle')
          .removeClass('dropdown-toggle')
          .css('border-radius', '4px')
      })
      this.$forceUpdate()
    }
  }
}
</script>

<style scoped>
/* Wider menu when media players are present */
.matching-dropdown-wide-menu {
  min-width: 480px !important;
  max-height: 60vh;
  overflow-y: auto;
}

/* Each option row */
.dropdown-match-item {
  padding: 6px 12px;
  border-bottom: 1px solid #e9ecef;
}
.dropdown-match-item:last-child {
  border-bottom: none;
}

/* "Clear selection" row shown once a match has been chosen */
.dropdown-match-item__clear {
  cursor: pointer;
  padding: 6px 0 6px 12px;
  color: #0d6efd;
  font-weight: 500;
  white-space: nowrap;
}
.dropdown-match-item__clear:hover {
  background-color: #f8f9fa;
  text-decoration: underline;
}

/* Text-only option */
.dropdown-match-item__text {
  cursor: pointer;
  padding: 4px 0;
}
.dropdown-match-item__text:hover {
  background-color: #f8f9fa;
}

/* Media (or mixed) row: player on top, Select button flush below */
.dropdown-match-item__media-row {
  display: flex;
  flex-direction: column;
  gap: 4px;
  padding: 8px 0;
}

/* Allow iframe interaction inside the menu */
.dropdown-match-item__player {
  display: block;
  pointer-events: auto;
}

/* Full-width Select button sits tight under the player */
.dropdown-match-item__select-btn {
  width: 100%;
}

/* Selected media player rendered below the dropdown toggle */
.chosen-media-player {
  width: 480px;
}

.chosen-media-player >>> iframe.question-media-player {
  display: block;
  min-height: 120px;
}
</style>
