/**
 * Shared, config-driven subject/chapter/section cascading dropdown logic.
 *
 * These functions replace the five Question-specific ones that used to live
 * in Questions.js (getQuestionSubjectIdOptions, getQuestionChapterIdOptions,
 * getQuestionSectionIdOptions, initAddEditDeleteQuestionSubjectChapterSection,
 * handleAddEditQuestionSubjectChapterSection). Both CreateQuestion.vue and
 * LearningTreeProperties.vue/learning_trees.editor.vue now call these same
 * functions, passing a small config object that says which form/options/URL
 * segment to use.
 *
 * IMPORTANT: subjects are shared vocabulary between Questions and Learning
 * Trees (same `question_subjects` table, same `/api/question-subjects`
 * endpoints - there is no separate "learning tree subject"). Chapters and
 * sections are also the same shared tables/endpoints
 * (`/api/question-chapters/...`, `/api/question-sections/...`) - a Learning
 * Tree and a Question can point at the exact same chapter/section row. The
 * "question-" prefix in the URLs and table names is legacy naming, not a
 * scoping mechanism; nothing here needs new backend tables or endpoints.
 *
 * Config shape (`sscConfig` - "subject/section/chapter config"):
 * {
 *   form:               string  - name of the form property on `this` that
 *                                  holds subject_id/chapter_id/section_id
 *                                  (e.g. 'questionForm', 'learningTreeForm')
 *   subjectIdOptions:   string  - name of the `this.*` array for subject options
 *   chapterIdOptions:   string  - name of the `this.*` array for chapter options
 *   sectionIdOptions:   string  - name of the `this.*` array for section options
 * }
 *
 * All functions are still called with `this` bound to the Vue component
 * instance (register them under `methods:` as before), and now also take
 * the config object as their first argument.
 */

import Form from 'vform/src'
import axios from 'axios'

export async function getSubjectIdOptions (sscConfig, subjectChapterQuestionManager = false) {
  try {
    const { data } = await axios.get('/api/question-subjects')
    this[sscConfig.subjectIdOptions] = subjectChapterQuestionManager ? [] : [{ value: null, text: 'Choose a subject' }]
    for (let i = 0; i < data.question_subjects.length; i++) {
      const questionSubject = data.question_subjects[i]
      this[sscConfig.subjectIdOptions].push({ value: String(questionSubject.id), text: questionSubject.name })
    }
  } catch (error) {
    this.$noty.error(error.message)
  }
}

export async function getChapterIdOptions (sscConfig, subjectId, subjectChapterQuestionManager = false) {
  if (subjectId !== null || subjectChapterQuestionManager) {
    try {
      const { data } = await axios.get(`/api/question-chapters/question-subject/${subjectId}`)
      this[sscConfig.chapterIdOptions] = subjectChapterQuestionManager ? [] : [{ value: null, text: 'Choose a chapter' }]
      for (let i = 0; i < data.question_chapters.length; i++) {
        const questionChapter = data.question_chapters[i]
        this[sscConfig.chapterIdOptions].push({ value: String(questionChapter.id), text: questionChapter.name })
      }
      if (subjectChapterQuestionManager) {
        this.$bvModal.show('modal-chapters')
      }
    } catch (error) {
      this.$noty.error(error.message)
    }
  }
}

export async function getSectionIdOptions (sscConfig, chapterId, subjectChapterQuestionManager = false) {
  if (chapterId !== null || subjectChapterQuestionManager) {
    try {
      const { data } = await axios.get(`/api/question-sections/question-chapter/${chapterId}`)
      this[sscConfig.sectionIdOptions] = subjectChapterQuestionManager ? [] : [{ value: null, text: 'Choose a section' }]
      for (let i = 0; i < data.question_sections.length; i++) {
        const questionSection = data.question_sections[i]
        this[sscConfig.sectionIdOptions].push({ value: String(questionSection.id), text: questionSection.name })
      }
      if (subjectChapterQuestionManager) {
        this.$bvModal.show('modal-sections')
      }
    } catch (error) {
      this.$noty.error(error.message)
    }
  }
}

export function initAddEditDeleteSubjectChapterSection (sscConfig, action, level) {
  this.questionSubjectChapterSectionToAddEditLevel = level
  this.questionSubjectChapterSectionAction = action
  if (['edit', 'add'].includes(this.questionSubjectChapterSectionAction)) {
    this.questionSubjectChapterSectionForm = new Form({ name: '' })
  }
  if (['edit', 'delete'].includes(this.questionSubjectChapterSectionAction)) {
    switch (level) {
      case ('subject'):
        this.questionSubjectChapterSectionToEditDeleteName = this[sscConfig.subjectIdOptions].find(item => item.value === this[sscConfig.form].question_subject_id).text
        break
      case ('chapter'):
        this.questionSubjectChapterSectionToEditDeleteName = this[sscConfig.chapterIdOptions].find(item => item.value === this[sscConfig.form].question_chapter_id).text
        break
      case ('section'):
        this.questionSubjectChapterSectionToEditDeleteName = this[sscConfig.sectionIdOptions].find(item => item.value === this[sscConfig.form].question_section_id).text
        break
      default:
        alert(`${level} does not yet exist as an option.`)
        return false
    }
    if (['edit', 'add'].includes(this.questionSubjectChapterSectionAction)) {
      this.questionSubjectChapterSectionForm.name = this.questionSubjectChapterSectionToEditDeleteName
    }
  }
  if (['edit', 'add'].includes(this.questionSubjectChapterSectionAction)) {
    this.$bvModal.show('modal-add-edit-question-subject-chapter-section')
  }
  if (this.questionSubjectChapterSectionAction === 'delete') {
    this.$bvModal.show('modal-confirm-delete-question-subject-chapter-section')
  }
}

export async function handleAddEditSubjectChapterSection (sscConfig, subjectChapterQuestionManager = false) {
  let action
  let url
  const form = this[sscConfig.form]
  switch (this.questionSubjectChapterSectionAction) {
    case ('add'):
      action = 'post'
      url = `/api/question-${this.questionSubjectChapterSectionToAddEditLevel}s`
      switch (this.questionSubjectChapterSectionToAddEditLevel) {
        case ('subject'):
          break
        case ('chapter'):
          url += `/question-subject/${form.question_subject_id}`
          break
        case ('section'):
          url += `/question-chapter/${form.question_chapter_id}`
          break
        default:
          this.$noty.error(`${this.questionSubjectChapterSectionToAddEditLevel} is not a level for adding.`)
          return false
      }
      break
    case ('edit'):
      action = 'patch'
      switch (this.questionSubjectChapterSectionToAddEditLevel) {
        case ('subject'):
          url = `/api/question-subjects/${form.question_subject_id}`
          break
        case ('chapter'):
          url = `/api/question-chapters/${form.question_chapter_id}`
          this.questionSubjectChapterSectionForm.question_subject_id = form.question_subject_id
          break
        case ('section'):
          url = `/api/question-sections/${form.question_section_id}`
          this.questionSubjectChapterSectionForm.question_chapter_id = form.question_chapter_id
          break
        default:
          this.$noty.error(`${this.questionSubjectChapterSectionToAddEditLevel} is not a level for editing.`)
          return false
      }
  }
  try {
    const { data } = await this.questionSubjectChapterSectionForm[action](url)
    this.$noty[data.type](data.message)
    if (data.type === 'success') {
      switch (this.questionSubjectChapterSectionAction) {
        case ('add'):
          switch (this.questionSubjectChapterSectionToAddEditLevel) {
            case ('subject'):
              await this.getSubjectIdOptions(sscConfig, subjectChapterQuestionManager)
              form.question_subject_id = String(data.question_level_id)
              form.question_chapter_id = null
              this[sscConfig.chapterIdOptions] = subjectChapterQuestionManager ? [] : [{
                value: null,
                text: 'Choose a chapter'
              }]
              form.question_section_id = null
              this[sscConfig.sectionIdOptions] = subjectChapterQuestionManager ? [] : [{
                value: null,
                text: 'Choose a section'
              }]
              break
            case ('chapter'):
              form.question_section_id = null
              this[sscConfig.sectionIdOptions] = subjectChapterQuestionManager ? [] : [{
                value: null,
                text: 'Choose a section'
              }]
              await this.getChapterIdOptions(sscConfig, form.question_subject_id, subjectChapterQuestionManager)
              form.question_chapter_id = String(data.question_level_id)
              break
            case ('section'):
              await this.getSectionIdOptions(sscConfig, form.question_chapter_id, subjectChapterQuestionManager)
              form.question_section_id = String(data.question_level_id)
              break
          }
          this.$forceUpdate()
          break
        case ('edit'):
          switch (this.questionSubjectChapterSectionToAddEditLevel) {
            case ('subject'):
              this[sscConfig.subjectIdOptions].find(item => item.value === form.question_subject_id).text = this.questionSubjectChapterSectionForm.name
              break
            case ('chapter'):
              this[sscConfig.chapterIdOptions].find(item => item.value === form.question_chapter_id).text = this.questionSubjectChapterSectionForm.name
              break
            case ('section'):
              this[sscConfig.sectionIdOptions].find(item => item.value === form.question_section_id).text = this.questionSubjectChapterSectionForm.name
              break
          }
      }
      this.$bvModal.hide('modal-add-edit-question-subject-chapter-section')
    }
  } catch (error) {
    if (!error.message.includes('status code 422')) {
      this.$noty.error(error.message)
    } else {
      this.allFormErrors = this.questionSubjectChapterSectionForm.errors.flatten()
      this.$bvModal.show('modal-form-errors-question-subject-chapter-section-errors')
    }
  }
}
