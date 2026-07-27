import axios from 'axios'
import { updateModalToggleIndex } from './accessibility/fixCKEditor'
import Form from 'vform/src'

export function isQtiOrForgeWithQtiAnswerSolution (item) {
  try {
    return !['forge', 'forge_iteration'].includes(JSON.parse(item.qti_answer_json).questionType) ||
      (['forge', 'forge_iteration'].includes(JSON.parse(item.qti_answer_json).questionType) &&
        typeof JSON.parse(item.qti_answer_json).solution_html !== 'undefined' &&
        JSON.parse(item.qti_answer_json).solution_html !== null)
  } catch {
    return false
  }
}

export function create3DModelSrc (parameters) {

  //No model url then add hideModel
  let src = 'https://devapp02.libretexts.org/?'
  if (parameters.modelID) {
    src += parameters.modelID ? 'modelID=' + parameters.modelID : 'BGImage' + parameters.BGImage
  }
  if (!parameters.modelID) {
    src += '&hideModel=1'
  }

  if (parameters.annotations) {
    src += '&annotations=' + parameters.annotations
  }
  src += '&mode=' + parameters.mode
  src += '&BGColor=' + (parameters.BGColor?.trim() || 'd3d3d3')
  if (parameters.modelOffset) {
    src += '&modelOffset=' + parameters.modelOffset
  }
  src += '&selectionColor=' + (parameters.selectionColor?.trim() || '0058E6')
  if (parameters.panel === 'no') {
    src += '&panel=hide'
  }
  if (parameters.autospin === 'no') {
    src += '&autospin=no'
  }
  src += '&STLmatCol=' + (parameters.STLmatCol?.trim() || 'ffffff')
  if (parameters.hideDistance) {
    src += '&hideDistance=' + parameters.hideDistance
  }
  src += '&v=' + Date.now()
  return src
}

export function formatQuestionMediaPlayer (htmlString) {
  const currentDomain = window.location.origin
  const regex = new RegExp(`<a\\s+href="${currentDomain}/question-media-player/([^"]+)">([^<]+)<\\/a>`, 'g')
  return htmlString.replace(regex, (match, url) => {
    return `<iframe class="question-media-player" style="width: 1px;min-width: 100%;" frameborder="0" src="${currentDomain}/question-media-player/${url}"></iframe></div>`
  })
}

/**
 * NOTE: getQuestionChapterIdOptions, getQuestionSubjectIdOptions,
 * getQuestionSectionIdOptions, handleAddEditQuestionSubjectChapterSection,
 * and initAddEditDeleteQuestionSubjectChapterSection used to live here.
 * They've moved to ./SubjectChapterSection.js as config-driven, shared
 * functions (getSubjectIdOptions, getChapterIdOptions, getSectionIdOptions,
 * handleAddEditSubjectChapterSection, initAddEditDeleteSubjectChapterSection)
 * so both CreateQuestion.vue and Learning Tree components can use the same
 * code. Import from './SubjectChapterSection' instead.
 */

export function capitalize (str) {
  if (!str) return ''
  return str.charAt(0).toUpperCase() + str.slice(1)
}

export async function handleDeleteQuestionSubjectChapterSection () {
  this.processing = true
  let url
  switch (this.questionSubjectChapterSectionToAddEditLevel) {
    case ('subject'):
      url = `/api/question-subjects/${this.questionForm.question_subject_id}`
      break
    case ('chapter'):
      url = `/api/question-chapters/${this.questionForm.question_chapter_id}`
      break
    case ('section'):
      url = `/api/question-sections/${this.questionForm.question_section_id}`
      break
    default:
      this.$noty.error(`${this.questionSubjectChapterSectionToAddEditLevel} is not a level for deleting.`)
      return false
  }
  try {
    const { data } = await axios.delete(url)
    this.$noty[data.type](data.message)
    this.processing = false
    if (data.type !== 'error') {
      switch (this.questionSubjectChapterSectionToAddEditLevel) {
        case ('subject'):
          this.questionSubjectIdOptions = this.questionSubjectIdOptions.filter(item => item.value !== this.questionForm.question_subject_id)
          break
        case ('chapter'):
          this.questionChapterIdOptions = this.questionChapterIdOptions.filter(item => item.value !== this.questionForm.question_chapter_id)
          break
        case ('section'):
          this.questionSectionIdOptions = this.questionSectionIdOptions.filter(item => item.value !== this.questionForm.question_section_id)
          break
      }

      this.$bvModal.hide('modal-confirm-delete-question-subject-chapter-section')
    }
  } catch (error) {
    this.$noty.error(error.message)
    this.processing = false
  }
}

/**
 * NOTE: handleAddEditQuestionSubjectChapterSection and
 * initAddEditDeleteQuestionSubjectChapterSection moved to
 * ./SubjectChapterSection.js as handleAddEditSubjectChapterSection and
 * initAddEditDeleteSubjectChapterSection (config-driven, shared with
 * Learning Trees). Import from there instead.
 */

/**
 * NOTE: getQuestionSubjectIdOptions and getQuestionSectionIdOptions moved
 * to ./SubjectChapterSection.js as getSubjectIdOptions and
 * getSectionIdOptions (config-driven, shared with Learning Trees).
 * Import from there instead.
 */

export function getTechnologySrc (technology, src, question) {
  let technologySrc = ''
  let text
  if (question[src]) {
    question[src] = question[src].replace('&amp;', '&')
    let url = new URL(question[src])
    switch (question[technology]) {
      case ('webwork'):
        text = url.searchParams.get('sourceFilePath')
        if (text) {
          if (text.length > 65) {
            text = text.slice(0, 65) + '...' + text.slice(text.length - 4)
          }
          technologySrc = `<a href="${question[src]}" target="”_blank”" >${text}</a>`
        }
        break
      case ('h5p'):
        text = question[src].replace('https://studio.libretexts.org/h5p/', '').replace('/embed', '')
        technologySrc = `<a href="${question[src].replace('/embed', '')}" target="”_blank”" ><img src="https://studio.libretexts.org/sites/default/files/LibreTexts_icon.png" alt="Libretexts logo" height="22" class="pb-1 pr-1">H5P Resource ID ${text} | LibreStudio</a>`
        break
      case ('imathas'):
        text = url.searchParams.get('id')
        technologySrc = `<a href="${question[src]}" target="”_blank”" >${text}</a>`
        break
      default:
        technologySrc = `Please Contact Us.  We have not yet implemented the sharing code for ${question[technology]}`
    }
    return technologySrc
  }
}

export function doCopy (adaptId) {
  this.$copyText(adaptId).then((e) => {
    this.$noty.success('The Question ID has been copied to your clipboard.')
  }, function (e) {
    this.$noty.error('We could not copy the Question ID to your clipboard.')
  })
}

export async function canEdit (isAdmin, user, question) {
  try {
    const { data } = await axios.get(`/api/co-question-editor/question/${question.id}/can-edit`)
    if (data.type === 'success') {
      if (data.can_edit) {
        return true
      }
    } else {
      this.$noty.error(data.message)
    }
  } catch (error) {
    this.$noty.error(error.message)
    return false
  }
  if (isAdmin || user.is_developer || user.role === 5) {
    return true
  } else {
    return question.library === 'adapt' && question.question_editor_user_id === user.id
  }
}

export async function editQuestionSource (question) {

  if (question.forge_source_id) {
    question = { ...question, question_id: question.forge_source_id, id: question.forge_source_id }
  }

  if (this.isBetaAssignment) {
    this.$bvModal.show('modal-should-not-edit-question-source-if-beta-assignment')
    return false
  }

  if (!await canEdit(this.isAdmin, this.user, question)) {
    console.error(question)
    if (question.technology !== 'webwork') {
      this.$noty.info('You cannot edit this question since you did not create it.')
      return false
    }
  }
  if (question.library === 'adapt') {
    await this.getQuestionToEdit(question)
    console.log(question)
    let modalId = `modal-edit-question-${question.id}`
    this.$bvModal.show(modalId)
    this.$nextTick(() => {
      updateModalToggleIndex(modalId)
    })
  } else {
    let mindtouchUrl = question.mindtouch_url ? question.mindtouch_url : `https://${question.library}.libretexts.org/@go/page/${question.page_id}`
    window.open(mindtouchUrl, '_blank')
  }
}

export async function getQuestionToEdit (questionToEdit) {
  console.log(questionToEdit)
  this.questionToEdit = questionToEdit
  try {
    const { data } = await axios.get(`/api/questions/get-question-to-edit/${questionToEdit.id}`)
    if (data.type === 'error') {
      this.$noty.error(data.message)
      return false
    }
    this.questionToEdit = data.question_to_edit
  } catch (error) {
    this.$noty.error(error.message)
  }
}

export async function getQuestionRevisionToEdit (revision) {
  if (!revision) {
    this.getQuestionToEdit(this.questionToEdit)
    return
  }
  try {
    const { data } = await axios.get(`/api/question-revisions/${revision}`)
    if (data.type === 'error') {
      this.$noty.error(data.message)
      return false
    }
    console.log(data)
    this.questionToEdit = data.question_revision
    this.revision = this.questionToEdit.question_revision_id
    this.$forceUpdate()
  } catch (error) {
    this.$noty.error(error.message)
  }
}

export function viewQuestion (questionId) {
  this.$router.push({ path: `/assignments/${this.assignmentId}/questions/view/${questionId}` })
  return false
}

export async function getQuestions () {
  this.questions = []
  try {
    const { data } = await axios.get(`/api/assignments/${this.assignmentId}/questions/summary`)
    if (!data.rows.length) {
      return false
    }
    for (let i = 0; i < data.rows.length; i++) {
      let question = data.rows[i]
      this.questions.push(question)
      this.questionsOptions.push({ value: question.order, text: question.order })
    }
    this.questionId = data.rows[0].question_id
    if (data.type === 'error') {
      this.$noty.error(data.message)
      return false
    }
  } catch (error) {
    this.$noty.error(error.message)
  }
  this.currentQuestionPage = 1
}

export const responseFormatOptions = [
  { text: 'Please choose the response format.', value: null },
  { text: 'Single Multiple Choice', value: 'multiple choice' },
  { text: 'Single Numeric Answer', value: 'numeric' },
  { text: 'Other', value: 'other' }
]

export const openEndedSubmissionTypeOptions = [
  { value: 'rich text', text: 'Rich Text' },
  { value: 'file', text: 'File' },
  { value: 'audio', text: 'Audio' },
  { value: 0, text: 'No submission, auto grading' },
  { value: 'no submission, manual grading', text: 'No submission, manual grading' }
]

