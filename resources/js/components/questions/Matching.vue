<template>
  <div>
    <ul class="pt-2 pl-0">
      <li v-for="(item, index) in termsToMatch" :key="`terms-to-match-${item.identifier}`"
          style="list-style: none;" class="pb-3"
      >
        <b-card header="default">
          <template #header>
            <span class="ml-2 h7">Matching {{ index + 1 }}</span>
            <span class="float-right"><b-icon-trash scale="1.5" @click="deleteMatchingTerm(item.identifier)"/></span>
          </template>
          <b-card-text>
            <b-row>
              <b-col>
                <b-form-group
                  :label-for="`qti_matching_term_to_match_${index}`"
                  class="mt-3"
                >
                  <template v-slot:label>
                    Term to match
                  </template>
                  <ckeditor
                    :id="`qti_matching_term_to_match_${index}`"
                    v-model="item.termToMatch"
                    tabindex="0"
                    :config="matchingRichEditorConfig"
                    @namespaceloaded="onCKEditorNamespaceLoaded"
                    @ready="handleFixCKEditor()"
                    @focus="questionForm.errors.clear(`qti_matching_term_to_match_${index}`)"
                  />
                  <ErrorMessage v-if="questionForm.errors.get(`qti_matching_term_to_match_${index}`)"
                                :message="questionForm.errors.get(`qti_matching_term_to_match_${index}`)"
                  />
                </b-form-group>
              </b-col>
              <b-col>
                <b-form-group
                  :label-for="`qti_matching_matching_term_${index}`"
                  class="mt-3"
                >
                  <template v-slot:label>
                    Matching term
                  </template>
                  <ckeditor
                    :id="`qti_matching_matching_term_${index}`"
                    v-model="possibleMatches.find(possibleMatch => possibleMatch.identifier === item.matchingTermIdentifier).matchingTerm"
                    tabindex="0"
                    :config="matchingRichEditorConfig"
                    @namespaceloaded="onCKEditorNamespaceLoaded"
                    @ready="handleFixCKEditor()"
                    @focus="questionForm.errors.clear(`qti_matching_matching_term_${index}`)"
                  />
                  <ErrorMessage v-if="questionForm.errors.get(`qti_matching_matching_term_${index}`)"
                                :message="questionForm.errors.get(`qti_matching_matching_term_${index}`)"
                  />
                </b-form-group>
              </b-col>
            </b-row>
            <b-form-group
              :label-for="`qti_matching_feedback_${index}`"
              class="mt-3"
            >
              <template v-slot:label>
                Feedback (Optional)
              </template>
              <ckeditor
                :id="`qti_matching_feedback_${index}`"
                v-model="item.feedback"
                tabindex="0"
                :config="matchingRichEditorConfig"
                @namespaceloaded="onCKEditorNamespaceLoaded"
                @ready="handleFixCKEditor()"
              />
            </b-form-group>
          </b-card-text>
        </b-card>
      </li>
    </ul>
    <div v-if="matchingDistractors.length">
      <hr>
      <ul class="pt-2 pl-0">
        <li v-for="(item, index) in matchingDistractors" :key="`terms-to-match-${item.identifier}`"
            style="list-style: none;" class="pb-3"
        >
          <b-alert show variant="secondary">
            <span class="ml-2 h7">Distractor {{ index + 1 }}</span>
            <span class="float-right"><b-icon-trash scale="1.5" @click="deleteDistractor(item.identifier)"/></span>
          </b-alert>
          <b-form-group>
            <ckeditor
              :id="`qti_matching_distractor_${index}`"
              v-model="item.matchingTerm"
              tabindex="0"
              :config="matchingRichEditorConfig"
              @namespaceloaded="onCKEditorNamespaceLoaded"
              @ready="handleFixCKEditor()"
              @focus="questionForm.errors.clear(`qti_matching_distractor_${index}`)"
            />
            <ErrorMessage v-if="questionForm.errors.get(`qti_matching_distractor_${index}`)"
                          :message="questionForm.errors.get(`qti_matching_distractor_${index}`)"
            />
          </b-form-group>
        </li>
      </ul>
    </div>
    <span class="mr-2">
      <b-button variant="primary"
                size="sm"
                @click="addQTIMatchingItem"
      >
        <span v-if="addingMatching"><b-spinner small type="grow"/>
          Adding...
        </span> <span v-if="!addingMatching">Add Matching</span>
      </b-button>
    </span>
    <b-button size="sm" @click="addQTIMatchingDistractor">
      <span v-if="addingDistractor"><b-spinner small type="grow"/>
        Adding...
      </span> <span v-if="!addingDistractor">
        Add Distractor</span>
    </b-button>
  </div>
</template>

<script>
import { v4 as uuidv4 } from 'uuid'
import CKEditor from 'ckeditor4-vue'
import { fixCKEditor } from '~/helpers/accessibility/fixCKEditor'
import ErrorMessage from '../ErrorMessage.vue'

export default {
  name: 'Matching',
  components: {
    ErrorMessage,
    ckeditor: CKEditor.component
  },
  props: {
    qtiJson: {
      type: Object,
      default: () => {
      }
    },
    questionForm: {
      type: Object,
      default: () => {
      }
    },
    matchingRichEditorConfig: {
      type: Object,
      default: () => {
      }
    }
  },
  data: () => ({
    addingMatching: false,
    addingDistractor: false,
    matchingDistractors: [],
    termsToMatch: [],
    possibleMatches: [],
    // Removing a matched option from the pool once chosen is no longer optional;
    // this is always true and is kept only so the saved QTI JSON still includes the flag.
    removeMatchedOptionsFromPool: true
  }),
  mounted () {
    let answerIdentifiers = []
    this.termsToMatch = this.qtiJson.termsToMatch
    for (let i = 0; i < this.termsToMatch.length; i++) {
      answerIdentifiers.push(this.termsToMatch[i].matchingTermIdentifier)
    }
    this.possibleMatches = this.qtiJson.possibleMatches
    for (let i = 0; i < this.possibleMatches.length; i++) {
      if (!answerIdentifiers.includes(this.possibleMatches[i].identifier)) {
        this.matchingDistractors.push(this.possibleMatches[i])
      }
    }
  },
  methods: {
    async addQTIMatchingDistractor () {
      this.addingDistractor = true
      let identifier = uuidv4()
      this.matchingDistractors.push({ identifier: identifier, matchingTerm: '' })
      this.possibleMatches.push({ identifier: identifier, matchingTerm: '' })
      this.addingDistractor = false
    },
    async addQTIMatchingItem () {
      this.addingMatching = true
      let matchingTermIdentifier = uuidv4()
      this.termsToMatch.push({
          identifier: uuidv4(),
          termToMatch: '',
          matchingTermIdentifier: matchingTermIdentifier,
          feedback: ''
        }
      )
      this.possibleMatches.push({
        identifier: matchingTermIdentifier,
        matchingTerm: ''
      })
      this.addingMatching = false
    },
    deleteMatchingTerm (identifier) {
      if (this.possibleMatches.length + this.matchingDistractors.length <= 2) {
        this.$noty.error('You need at least 2 possible matches.')
        return false
      }
      let matchingTermIdentifier = this.termsToMatch.find(termToMatch => termToMatch.identifier === identifier).matchingTermIdentifier
      this.possibleMatches = this.possibleMatches.filter(possibleMatch => possibleMatch.identifier !== matchingTermIdentifier)
      this.termsToMatch = this.termsToMatch.filter(termToMatch => termToMatch.identifier !== identifier)
    },
    deleteDistractor (identifier) {
      if (this.possibleMatches.length + this.matchingDistractors.length <= 2) {
        this.$noty.error('You need at least 2 possible matches.')
        return false
      }
      this.matchingDistractors = this.matchingDistractors.filter(distractor => distractor.identifier !== identifier)
      this.possibleMatches = this.possibleMatches.filter(possibleMatch => possibleMatch.identifier !== identifier)
      this.$forceUpdate()
    },
    handleFixCKEditor () {
      fixCKEditor(this)
    },
    onCKEditorNamespaceLoaded (CKEDITOR) {
      CKEDITOR.addCss('.cke_editable { font-size: 15px; }')
    }
  }
}
</script>

<style scoped>

</style>
