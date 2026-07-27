<template>
  <div>
    <PageTitle title="Subject–Chapter–Section Manager"/>
    <b-modal id="modal-confirm-delete-question-subject-chapter-section"
             :title="`Delete ${capitalize(questionSubjectChapterSectionToAddEditLevel)}`"
             no-close-on-backdrop
    >
      <p>Please confirm that you would like to delete <strong>{{ questionSubjectChapterSectionToEditDeleteName}}</strong>.</p>
      <p v-show="questionSubjectChapterSectionToAddEditLevel === 'subject'">
        All associated chapters and sections will also be deleted and removed from the question meta-tags.
      </p>
      <p v-show="questionSubjectChapterSectionToAddEditLevel === 'chapter'">
        All associated sections will also be deleted and removed from the question meta-tags.
      </p>
      <p v-show="questionSubjectChapterSectionToAddEditLevel === 'section'">
        The information will also be removed from the question meta-tags.
      </p>
      <template #modal-footer>
        <b-button
          size="sm"
          @click="$bvModal.hide('modal-confirm-delete-question-subject-chapter-section')"
        >
          Cancel
        </b-button>
        <b-button
          v-if="!processing"
          size="sm"
          variant="danger"
          @click="handleDeleteQuestionSubjectChapterSection()"
        >
          Delete
        </b-button>
        <span v-if="processing">
             <b-spinner small type="grow"/>
          Processing...
        </span>
      </template>

    </b-modal>
    <b-modal id="modal-add-edit-question-subject-chapter-section"
             :title="`${capitalize(questionSubjectChapterSectionAction)} ${capitalize(questionSubjectChapterSectionToAddEditLevel)}`"
             no-close-on-backdrop
             size="lg"
    >
      <b-form-group
        label-cols-sm="2"
        label-cols-lg="1"
        label-for="level"
        label-align="center"
        label="Name"
      >
        <b-form-input v-model="questionSubjectChapterSectionForm.name"
                      required
                      :class="{ 'is-invalid': questionSubjectChapterSectionForm.errors.has('name')}"
                      @keydown="questionSubjectChapterSectionForm.errors.clear('name')"
        />
        <has-error :form="questionSubjectChapterSectionForm" field="name"/>
      </b-form-group>
      <template #modal-footer>
        <b-button
          size="sm"
          @click="$bvModal.hide('modal-add-edit-question-subject-chapter-section')"
        >
          Cancel
        </b-button>
        <b-button
          size="sm"
          variant="primary"
          @click="handleAddEditQuestionSubjectChapterSection"
        >
          Save
        </b-button>
      </template>
    </b-modal>
    <b-modal id="modal-sections"
             title="Sections"
             size="lg"
    >
      <div class="mb-3">
        <b-button size="sm"
                  variant="outline-primary"
                  class="mt-2"
                  @click="initAddEditDeleteQuestionSubjectChapterSection('add','section')"
        >
          Add
        </b-button>
      </div>
      <div v-if="questionSectionIdOptions.length">
        <b-table
          :items="questionSectionIdOptions"
          :fields="sectionFields"
          small
          bordered
          responsive
        >
          <template #cell(actions)="row">
            <b-button size="sm"
                      variant="outline-info"
                      @click="questionForm.question_section_id = row.item.value;initAddEditDeleteQuestionSubjectChapterSection('edit','section')"
            >
              <b-icon-pencil></b-icon-pencil>
            </b-button>
            <b-button size="sm"
                      variant="outline-danger"
                      class="ml-1"
                      @click="questionForm.question_section_id = row.item.value;initAddEditDeleteQuestionSubjectChapterSection ('delete', 'section')">
              <b-icon-trash></b-icon-trash>
            </b-button>
          </template>
        </b-table>
      </div>
      <div v-else>
        <b-alert show>
          There are currently no chapters associated with this subject.
        </b-alert>
      </div>
      <template #modal-footer>
        <b-button
          variant="primary"
          size="sm"
          @click="$bvModal.hide('modal-sections')"
        >
         OK
        </b-button>
      </template>
    </b-modal>
    <b-modal id="modal-chapters"
             title="Chapters"
             size="lg"
    >
      <div class="mb-3">
        <b-button size="sm"
                  variant="outline-primary"
                  class="mt-2"
                  @click="initAddEditDeleteQuestionSubjectChapterSection('add','chapter')"
        >
          Add
        </b-button>
      </div>
      <div v-if="questionChapterIdOptions.length">
        <b-table
          :items="questionChapterIdOptions"
          :fields="chapterFields"
          small
          bordered
          responsive
        >
          <template #cell(actions)="row">
            <b-button size="sm"
                      variant="outline-info"
                      @click="questionForm.question_chapter_id = row.item.value;initAddEditDeleteQuestionSubjectChapterSection('edit','chapter')"
            >
              <b-icon-pencil></b-icon-pencil>
            </b-button>
            <b-button size="sm" variant="outline-info" class="ml-1"
                      @click="questionForm.question_chapter_id = row.item.value;getQuestionSectionIdOptions(row.item.value, true)"
            >
              <b-icon-folder></b-icon-folder>
            </b-button>
            <b-button size="sm" variant="outline-danger" class="ml-1"
                      @click="questionForm.question_chapter_id = row.item.value;initAddEditDeleteQuestionSubjectChapterSection('delete','chapter')"
            >
              <b-icon-trash></b-icon-trash>
            </b-button>
          </template>
        </b-table>
      </div>
      <div v-else>
        <b-alert show>
          There are currently no chapters associated with this subject.
        </b-alert>
      </div>
      <template #modal-footer>
        <b-button
          variant="primary"
          size="sm"
          @click="$bvModal.hide('modal-chapters')"
        >
          OK
        </b-button>
      </template>
    </b-modal>
    <div class="pb-3">
      <b-button size="sm"
                variant="outline-primary"
                class="mt-2"
                @click="initAddEditDeleteQuestionSubjectChapterSection('add','subject')"
      >
        Add
      </b-button>
    </div>
    <b-table
      :items="questionSubjectIdOptions"
      :fields="fields"
      small
      bordered
      responsive
    >
      <template #cell(actions)="row">
        <b-button size="sm"
                  variant="outline-info"
                  @click="questionForm.question_subject_id = row.item.value;initAddEditDeleteQuestionSubjectChapterSection('edit','subject')"
        >
          <b-icon-pencil></b-icon-pencil>
        </b-button>
        <b-button size="sm"
                  variant="outline-info"
                  class="ml-1"
                  @click="questionForm.question_subject_id = row.item.value;getQuestionChapterIdOptions(row.item.value, true)"
        >
          <b-icon-folder></b-icon-folder>
        </b-button>
        <b-button size="sm"
                  variant="outline-danger"
                  class="ml-1"
                  @click="questionForm.question_subject_id = row.item.value;initAddEditDeleteQuestionSubjectChapterSection ('delete', 'subject')"
        >
          <b-icon-trash></b-icon-trash>
        </b-button>
      </template>
    </b-table>
  </div>
</template>

<script>
import Form from 'vform'
import { capitalize } from '../../helpers/Questions'
import {
  getSubjectIdOptions,
  getChapterIdOptions,
  getSectionIdOptions,
  handleAddEditSubjectChapterSection,
  initAddEditDeleteSubjectChapterSection
} from '../../helpers/SubjectChapterSection'

export default {
  name: 'subjectChapterSectionManager',
  data: () => ({
    processing: false,
    questionSubjectChapterSectionAction: '',
    questionSubjectChapterSectionForm: new Form({}),
    questionSubjectChapterSectionToAddEditLevel: '',
    questionSubjectChapterSectionToEditDeleteName: '',
    questionChapterIdOptions: [],
    questionSubjectIdOptions: [],
    questionSectionIdOptions: [],
    questionForm: new Form({}),
    fields: [
      { key: 'text', label: 'Subject' },
      { key: 'actions', label: 'Actions', thStyle: { width: '160px' }, tdClass: 'text-center' }
    ],
    chapterFields: [
      { key: 'text', label: 'Chapter' },
      { key: 'actions', label: 'Actions', thStyle: { width: '160px' }, tdClass: 'text-center' }
    ],
    sectionFields: [
      { key: 'text', label: 'Section' },
      { key: 'actions', label: 'Actions', thStyle: { width: '160px' }, tdClass: 'text-center' }
    ]
  }),
  mounted () {
    this.getQuestionSubjectIdOptions(true)
  },
  methods: {
    capitalize,
    // Config for the shared helpers in ~/helpers/SubjectChapterSection.js -
    // same shape/idea as CreateQuestion.vue's and LearningTreeProperties.vue's
    // config, pointed at this page's own questionForm/question*IdOptions.
    subjectChapterSectionConfig () {
      return {
        form: 'questionForm',
        subjectIdOptions: 'questionSubjectIdOptions',
        chapterIdOptions: 'questionChapterIdOptions',
        sectionIdOptions: 'questionSectionIdOptions'
      }
    },
    async getQuestionSubjectIdOptions (subjectChapterQuestionManager = false) {
      await getSubjectIdOptions.call(this, this.subjectChapterSectionConfig(), subjectChapterQuestionManager)
    },
    async getQuestionChapterIdOptions (subjectId, subjectChapterQuestionManager = false) {
      await getChapterIdOptions.call(this, this.subjectChapterSectionConfig(), subjectId, subjectChapterQuestionManager)
    },
    async getQuestionSectionIdOptions (chapterId, subjectChapterQuestionManager = false) {
      await getSectionIdOptions.call(this, this.subjectChapterSectionConfig(), chapterId, subjectChapterQuestionManager)
    },
    async handleAddEditQuestionSubjectChapterSection (subjectChapterQuestionManager = false) {
      await handleAddEditSubjectChapterSection.call(this, this.subjectChapterSectionConfig(), subjectChapterQuestionManager)
    },
    initAddEditDeleteQuestionSubjectChapterSection (action, level) {
      initAddEditDeleteSubjectChapterSection.call(this, this.subjectChapterSectionConfig(), action, level)
    },
    // NOTE: handleDeleteQuestionSubjectChapterSection was left untouched in
    // Questions.js (it's hardcoded to questionForm/question*IdOptions
    // already, which happens to match this page's own naming exactly, so
    // it still works unmodified - no wrapper needed for this one).
    handleDeleteQuestionSubjectChapterSection,
    deleteSection () {

    }
  }
}
</script>
