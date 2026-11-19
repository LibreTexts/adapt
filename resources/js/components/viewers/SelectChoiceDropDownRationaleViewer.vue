<template>
  <div>
    <div v-if="qtiJson.questionType === 'drop_down_rationale_triad' && qtiJson.jsonType === 'answer_json'">
      <b-alert variant="info" show>
        The correct answers in the second and third drop-downs are interchangeable.
      </b-alert>
    </div>
    <form class="form-inline">
      <div v-html="addSelectChoices"/>
    </form>
    <GeneralFeedback v-if="qtiJson.jsonType === 'question_json'"
                     :feedback="qtiJson.feedback"
                     :feedback-type="feedbackType"
    />
  </div>
</template>

<script>

import $ from 'jquery'
import { successIcon, failureIcon } from '~/helpers/SuccessFailureIcons'
import GeneralFeedback from '../feedback/GeneralFeedback'
import { formatQuestionMediaPlayer } from '~/helpers/Questions'

export default {
  name: 'SelectChoiceDropDownRationaleViewer',
  components: {
    GeneralFeedback
  },
  props: {
    qtiJson: {
      type: Object,
      default: () => {
      }
    },
    showResponseFeedback: {
      type: Boolean,
      default: true
    },
    mediaUploads: {
      type: Array,
      default: () => []
    }
  },
  data: () => ({
    successIcon: successIcon,
    failureIcon: failureIcon,
    selectChoicesArray: [],
    selectedOption: [],
    encodedStr: '',
    encoding: true,
    feedbackType: 'incorrect'
  }),
  computed: {
    addSelectChoices () {
      if (this.qtiJson.itemBody) {
        if (this.qtiJson.questionType === 'drop_down_rationale_triad') {
          this.qtiJson.itemBody = this.qtiJson.itemBody.replace('[rationale]', '[rationale-1]')
          this.qtiJson.itemBody = this.qtiJson.itemBody.replace('[rationale]', '[rationale-2]')
          this.qtiJson.inline_choice_interactions['rationale-1'] = this.qtiJson.inline_choice_interactions['rationales']
          this.qtiJson.inline_choice_interactions['rationale-2'] = this.qtiJson.inline_choice_interactions['rationales']
          delete (this.qtiJson.inline_choice_interactions['rationales'])
        }
        if (this.qtiJson.questionType === 'select_choice') {
          this.qtiJson.itemBody = this.formatQuestionMediaPlayer(this.qtiJson.itemBody, this.mediaUploads)
        }
        console.log(this.qtiJson.itemBody)
        let reg = /\[(.*?)\]/g
        let selectChoicesArray = this.qtiJson.itemBody.split(reg)
        console.log(selectChoicesArray)
        console.log(this.qtiJson.inline_choice_interactions)
        let html = ''
        for (let i = 0; i < selectChoicesArray.length; i++) {
          let part = selectChoicesArray[i]
          if (i % 2 === 0) {
            html += part
          } else {
            // The server runs itemBody through DOMDocument (Question::addTimeToS3Files),
            // which re-encodes every non-ASCII character as a numeric entity
            // (e.g. "laŋlaŋ" -> "la&#331;la&#331;", "'" -> "&#39;"), while the keys of
            // inline_choice_interactions stay as raw UTF-8. Match on the decoded form.
            if (!this.qtiJson.inline_choice_interactions[part]) {
              const decodedPart = this.decodeHtmlEntities(part).normalize('NFC')
              const matchingKey = Object.keys(this.qtiJson.inline_choice_interactions)
                .find(key => this.decodeHtmlEntities(key).normalize('NFC') === decodedPart)
              if (matchingKey !== undefined) {
                part = matchingKey
              }
            }
            if (!this.qtiJson.inline_choice_interactions[part]) {
              console.error(`SelectChoiceDropDownRationaleViewer: no inline_choice_interactions entry for blank "${part}". Check for an encoding mismatch (e.g. an apostrophe) between itemBody and inline_choice_interactions.`)
              html += `<span class="text-danger">[Missing answer options for "${part}"]</span>`
              continue
            }
            let studentResponse
            // If there is an issue with not seeing the correct answer for select choices, it's because I used to do the identifier by
            // time stamp and I'm not 100% sure of uniqueness. This was changed at some point to use the uuid4()
            if (this.qtiJson.dropDownCloze) {
              studentResponse = this.qtiJson.studentResponse ? this.qtiJson.studentResponse.find(item => item.identifier === part) : ''
            } else {
              if (this.qtiJson.studentResponse) {
                studentResponse = this.qtiJson.studentResponse.find(item => item.identifier === part)
              }
              if (!studentResponse) {
                studentResponse = this.qtiJson.studentResponse ? this.qtiJson.studentResponse[Math.floor(i / 2)] : ''
              }
            }
            let chosenOption = studentResponse ? studentResponse.value : ''
            console.log(chosenOption)
            // The identifier goes in a data attribute (HTML-escaped) so that spaces, quotes,
            // ampersands, etc. in the blank text survive; the class is kept for backwards
            // compatibility but with whitespace and quotes stripped so it can't break the markup.
            html += `<select style="margin:3px;width: 200px"
data-identifier="${this.escapeHtmlAttribute(part)}"
class="identifier-${part.replace(/[\s"'<>&]/g, '_')} select-choice custom-select custom-select-sm form-control inline-form-control"
aria-label="combobox ${Math.ceil(i / 2)} of ${Math.floor(selectChoicesArray.length / 2)}">
<option value="">Please select an option</option>`
            for (let i = 0; i < this.qtiJson.inline_choice_interactions[part].length; i++) {
              let selectChoice = this.qtiJson.inline_choice_interactions[part][i]
              let selected = selectChoice.value === chosenOption ? 'selected' : ''
              html += `<option style="width: 100%;" value="${selectChoice.value}" ${selected}>${selectChoice.text}</option>`
            }
            html += '</select>'
            if (this.qtiJson.jsonType === 'question_json' && studentResponse && studentResponse.hasOwnProperty('answeredCorrectly')) {
              html += studentResponse.answeredCorrectly ? this.successIcon : this.failureIcon
            }
          }
        }
        return html
      } else {
        return []
      }
    }
  },
  mounted () {
    this.$nextTick(() => {
      for (let i = 0; i < Math.floor(this.selectChoicesArray.length / 2); i++) {
        let selected = this.qtiJson.studentResponse ? this.qtiJson.studentResponse[i].value : ''
        this.selectedOption.push(selected)
      }
      if (this.qtiJson.studentResponse) {
        this.feedbackType = this.qtiJson.studentResponse.filter(response => response.answeredCorrectly).length === this.qtiJson.studentResponse.length
          ? 'correct'
          : 'incorrect'
      }
      console.log(this.selectedOption)
      MathJax.startup.promise.then(() => {
        // MathJax was double rendering the equations so I'm just keeping the assisted technology version
        const selects = document.querySelectorAll('select[class^="identifier-"]')

        selects.forEach(select => {
          const options = select.querySelectorAll('option')

          options.forEach(option => {
            const mathElements = option.querySelectorAll('.math')
            mathElements.forEach(mathElement => {
              mathElement.remove()
            })
          })
        })
      })
    })
    $(document).on('change', 'select.select-choice', function () {
      $(this).removeClass('is-invalid-border')
    })
    $('p:empty').remove()
    this.$forceUpdate()
  },
  methods: {
    decodeHtmlEntities (str) {
      const textarea = document.createElement('textarea')
      textarea.innerHTML = str
      return textarea.value
    },
    escapeHtmlAttribute (str) {
      return String(str)
        .replace(/&/g, '&amp;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
    },
    formatQuestionMediaPlayer
  }
}
</script>
