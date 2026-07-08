<template>
  <div style="padding-bottom: 60px; margin-top: -30px;">
    <PageTitle v-if="!inIFrame" title="Edit Question"/>
    <div v-if="isLoading"
         class="d-flex justify-content-center align-items-center"
         style="min-height: 400px;"
    >
      <b-spinner variant="primary" style="width: 128px; height: 128px;" label="Loading..."/>
    </div>
    <CreateQuestion v-if="questionToEdit.id"
                    :question-to-edit="questionToEdit"
                    :question-media-upload-id="+questionMediaUploadId"
                    :modal-id="'edit-question'"
                    :parent-get-my-questions="notifyParent"
    />
  </div>
</template>

<script>
import CreateQuestion from '~/components/questions/CreateQuestion'
import axios from 'axios'

export default {
  components: {
    CreateQuestion
  },
  data: () => ({
    questionToEdit: {},
    questionMediaUploadId: 0,
    isLoading: true,
    inIFrame: false
  }),
  mounted () {
    try {
      this.inIFrame = window.self !== window.top
    } catch (e) {
      this.inIFrame = true
    }
    this.getQuestionToEdit(this.$route.params.questionId)
    if (this.$route.params.questionMediaUploadId) {
      this.questionMediaUploadId = this.$route.params.questionMediaUploadId
    }
  },
  methods: {
    async getQuestionToEdit (questionId) {
      try {
        const { data } = await axios.get(`/api/questions/get-question-to-edit/${questionId}`)
        if (data.type === 'error') {
          this.$noty.error(data.message)
          return false
        }
        this.questionToEdit = data.question_to_edit
      } catch (error) {
        this.$noty.error(error.message)
      }
      this.isLoading = false
    },
    notifyParent () {
      window.parent.postMessage('question-saved', '*')
    }
  }
}
</script>
