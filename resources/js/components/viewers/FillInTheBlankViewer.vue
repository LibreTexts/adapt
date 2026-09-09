<template>
  <div class="pb-2">
    <form class="form-inline">
      <div v-html="addFillInTheBlanks"/>
    </form>
  </div>
</template>

<script>
import $ from 'jquery'
import { successIcon, failureIcon } from '~/helpers/SuccessFailureIcons'
import { formatQuestionMediaPlayer } from '~/helpers/Questions'

export default {
  name: 'FillInTheBlankViewer',
  props: {
    qtiJson: {
      type: Object,
      default: () => {
      }
    },
    showResponseFeedback: {
      type: Boolean,
      default: true
    }
  },
  data: () => ({
    fillInTheBlankArray: []
  }),
  computed: {
    addFillInTheBlanks () {
      if (this.qtiJson.itemBody) {
        const reg = /(<u>.*?<\/u>)/
        let fillInTheBlankArray = this.qtiJson.itemBody.textEntryInteraction.split(reg)
        console.log(fillInTheBlankArray)
        let html = ''
        let responseIndex = 1
        for (let i = 0; i < fillInTheBlankArray.length; i++) {
          let part = fillInTheBlankArray[i]
          if (i % 2 === 0) {
            html += part.replace('<u>', '').replace('</u>', '')
          } else {
            let studentResponse = this.qtiJson.studentResponse ? this.qtiJson.studentResponse[responseIndex - 1] : null
            let studentResponseValue = studentResponse ? studentResponse.value : ''
            html += `<input  type="text" class="response_${responseIndex} fill-in-the-blank form-control form-control-sm" value="${studentResponseValue}"/>`
            if (studentResponse &&
              studentResponse.hasOwnProperty('answeredCorrectly') &&
              this.showResponseFeedback) {
              html += '<span class="pl-1">'
              html += studentResponse.answeredCorrectly ? successIcon : failureIcon
              html += '</span>'
            }
            responseIndex++
          }
        }
        return this.formatQuestionMediaPlayer(html)
      }
    }
  },
  mounted () {
    $(document).on('keydown', 'input.fill-in-the-blank', function () {
      $(this).removeClass('is-invalid-border')
    })
  },
  methods: {
    formatQuestionMediaPlayer,
    isCorrect (index) {
      return this.qtiJson.studentResponse[index].answeredCorrectly
    },
    removeUnderline (item) {
      return item.replace('<u>', '').replace('</u>', '').replace('<p>', '').replace('</p>', '')
    },
    getFillInTheBlankResponseDeclarations () {
      let responseDeclarations = []
      console.log(this.uTags)
      if (this.uTags) {
        for (let i = 0; i < this.uTags.length; i++) {
          let uTag = this.uTags[i]
          console.log(uTag)
          let responseDeclaration = {
            'value': uTag,
            'matchingType': this.textEntryInteractions[i].matchingType,
            'caseSensitive': this.textEntryInteractions[i].caseSensitive
          }
          responseDeclarations.push(responseDeclaration)
        }
      }
      return responseDeclarations
    }
  }
}
</script>
<style scoped>
.form-inline > div {
  flex: 1 1 100%;
  width: 100%;
}
</style>
