import axios from 'axios'
import {
  h5pOnLoadCssUpdates,
  h5pStudentCssUpdates,
  webworkOnLoadCssUpdates,
  webworkStudentCssUpdates,
  applyWarningsVisibility
} from './CSSUpdates'

export function getTechnology (body) {
  let technology
  if (body === 'qti') {
    technology = 'qti'
  } else if (body.includes('h5p.libretexts.org') || body.includes('studio.libretexts.org')) {
    technology = 'h5p'
  } else if (body.includes('imathas.libretexts.org')) {
    technology = 'imathas'
  } else if (body.includes('render.libretexts.org') || body.includes('staging-render.libretexts.org') || body.includes('wwrenderer-staging.libretexts.org') || body.includes('wwrenderer.libretexts.org') || body.includes('webwork.libretexts.org') || (body.includes('demo.webwork.rochester.edu'))) {
    technology = 'webwork'
  } else {
    technology = false
  }
  return technology
}

export async function getTechnologySrcDoc (vm, url, assignmentId, questionId, table, learningTreeId = null) {
  try {
    const { data } = await axios.post(`/api/webwork/src-doc/assignment/${assignmentId}/question/${questionId}`, {
      url: url,
      table: table,
      learning_tree_id: learningTreeId
    })
    if (data.type === 'error') {
      this.$noty.error(data.message)
      return false
    }
    vm.technologySrcDoc = data.src_doc
    vm.submissionArray = data.submission_array
  } catch (error) {
    this.$noty.error(error.message)
  }
}

export async function hideSubmitButtonsIfCannotSubmit (vm, routeName, technology, updatedLastSubmittedInfo = false) {
  console.log('running hide submit buttons if cannot submit')
  const question = getQuestionBasedOnRoute(vm, routeName)
  if (!technology) {
    // vm will happen with webwork since there is no url
    technology = question && question.technology
  }
  console.log(`technology: ${technology}`)
  switch (technology) {
    case ('h5p'):
      if (vm.user.role === 3) {
        console.log('a')
        console.log(!vm.submitButtonActive)
        console.log(!vm.submitButtonsDisabled)
        console.log('b')
        if (!vm.submitButtonActive && !vm.submitButtonsDisabled) {
          vm.submitButtonsDisabled = true
          vm.event.source.postMessage(JSON.stringify(h5pStudentCssUpdates), vm.event.origin)
        }
      }
      if (vm.event && (vm.event.data === '"loaded"' || vm.event.data === 'loaded')) {
        vm.iframeDomLoaded = true
        let cssUpdates = h5pOnLoadCssUpdates
        if (vm.user.role === 3) {
          cssUpdates.elements.push({
            selector: '.h5p-actions',
            style: 'display:none;'
          })
        }
        vm.event.source.postMessage(JSON.stringify(cssUpdates), vm.event.origin)
      }
      break
    case ('native'):
      vm.iframeDomLoaded = true
      break
    case ('webwork'):
      console.log('receiveMessage from webwork')
      let jsonObj = {}
      try {
        jsonObj = JSON.parse(vm.event.data)
        console.log(jsonObj.solutions)
      } catch (error) {
        console.log('Not an object:' + vm.event.data)
      }
      console.error('a')
      console.error(vm.event.data)
      if (jsonObj.type === 'webwork.lifecycle.loaded' || updatedLastSubmittedInfo) {
        // just do it on these 2 events or it will happen 50 million times and the browser will crash
        vm.iframeDomLoaded = true
        applyWarningsVisibility(vm.user)
        vm.event.source.postMessage(JSON.stringify(webworkOnLoadCssUpdates), vm.event.origin)
        console.log('webwork css applied')
        vm.addGlow(vm.event, vm.submissionArray, 'webwork')
        console.log('glow added')
        if (vm.user.role === 3) {
          if (!vm.submitButtonActive && !vm.submitButtonsDisabled) {
            vm.submitButtonsDisabled = true
            vm.event.source.postMessage(JSON.stringify(webworkStudentCssUpdates), vm.event.origin)
          }
        }
      }
      break
    default:
  }
}

export function addGlow (event, submissionArray, technology) {
  console.log('adding glow')
  console.log(submissionArray)
  switch (technology) {
    case ('imathas'):
      let raw = []
      for (let i = 0; i < submissionArray.length; i++) {
        raw.push(+submissionArray[i].correct)
      }
      console.log('source')
      console.log(submissionArray)
      event.source.postMessage(JSON.stringify({ raw: raw }), event.origin)

      break
    case ('webwork'):
      let elements
      elements = []
      for (let i = 0; i < submissionArray.length; i++) {
        let identifier = submissionArray[i].identifier
        let correct = submissionArray[i].correct
        let color = correct ? '#519951cc' : '#bf545499'
        elements.push({
          selector: `#mq-answer-${identifier}`,
          style: `border-color: ${color};box-shadow: inset 0 1px 1px rgba(0,0,0,.075),0 0 8px ${color};color: inherit;outline: 0;`
        })
      }
      let glowCss = { elements: elements }

      event.source.postMessage(JSON.stringify(glowCss), event.origin)

      break
  }
}

function getQuestionBasedOnRoute (vm, routeName) {
  let question
  switch (routeName) {
    case ('questions.view'):
      question = vm.questions[vm.currentPage - 1]
      break
    case ('instructors.learning_trees.editor'):
      question = vm.nodeQuestion
      break
    default:
      console.log(`This route is not set up for processReceiveMessage: ${routeName}`)
      return false
  }
  return question
}

export async function processReceiveMessage (vm, routeName, event) {
  console.log(routeName)
  const question = getQuestionBasedOnRoute(vm, routeName)
  let technology = getTechnology(event.origin)
  vm.event = event
  await hideSubmitButtonsIfCannotSubmit(vm, routeName, technology)

  if (!vm.isAnonymousUser) {
    if (technology === 'imathas') {

    }
    let clientSideSubmit
    let serverSideSubmit
    let iMathASResize
    let h5pErrorMessage = 'Error receiving response from H5P.  Please contact your instructor to verify that the question is working properly on their end. If the question is not supported by ADAPT at this time, they may have to remove the question from the assignment.'
    try {
      // console.log(event)
      let isAnsweredH5p = false
      if (vm.user.role === 3 && technology === 'h5p') {
        // check that the event is actually an xAPI statement
        if (typeof event.data === 'string' && event.data !== '"loaded"' && event.data !== 'loaded' && event.data !== 'updated elements') {
          let h5pEventObject = JSON.parse(event.data)
          if (h5pEventObject.hasOwnProperty('verb')) {
            console.log('event object for h5p')
            console.log(h5pEventObject)
            isAnsweredH5p = h5pEventObject.verb.id === 'http://adlnet.gov/expapi/verbs/answered'
            if (vm.ish5pActivitySet && isAnsweredH5p && !('response' in h5pEventObject.result)) {
              isAnsweredH5p = false // what is seen on the final screen for a submission with a video interaction
              console.info('Final screen for video interaction so no submit')
            }
            if (routeName === 'questions.view' && !isAnsweredH5p) {
              if (!vm.ish5pActivitySet) {
                vm.numberOfRemainingAttempts = vm.getNumberOfRemainingAttempts()
              }
            }
          } else if (h5pEventObject.hasOwnProperty('maxScore') && !vm.maxScore) {
            vm.maxScore = h5pEventObject.maxScore
            console.error(`Max score set: ${vm.maxScore}`)
          } else {
            console.log('Nothing to set for question')
            console.log(h5pEventObject)
          }
        }
      }
      clientSideSubmit = technology === 'qti' || isAnsweredH5p
    } catch (error) {
      alert(h5pErrorMessage)
      clientSideSubmit = false
      console.error(error)
    }
    try {
      serverSideSubmit = ((technology === 'imathas' && JSON.parse(event.data).subject === 'lti.ext.imathas.result') ||
        (technology === 'webwork' && JSON.parse(event.data).type === 'webwork.interaction.submitted'))
      // console.error('checking server side')
      // console.error(serverSideSubmit)
      // console.error('good')
      // console.error(technology)
      // console.error(event.data)
    } catch (error) {
      // console.error('bad')
      // console.error(event.data)
      serverSideSubmit = false
    }

    try {
      iMathASResize = ((technology === 'imathas') && (JSON.parse(event.data).subject === 'lti.frameResize'))
    } catch (error) {
      iMathASResize = false
    }
    if (iMathASResize) {
      let embedWrap = document.getElementById('embed1wrap')
      if (embedWrap) {
        embedWrap.setAttribute('height', JSON.parse(event.data).wrapheight)
        if (embedWrap.getElementsByTagName('iframe')) {
          let iframe = embedWrap.getElementsByTagName('iframe')[0]
          iframe.setAttribute('height', JSON.parse(event.data).height)
        }
      }
    }
    console.log(question)
    let isLearningTreeNode = question &&
      vm.learningTreeId &&
      parseInt(vm.activeId) !== 0
    if (serverSideSubmit) {
      question.can_give_up = true
      let data = {}
      try {
        data = JSON.parse(event.data)
        if (technology === 'webwork' && data.type === 'webwork.interaction.submitted') {
          let verdict = data.status
          if (verdict.status >= 300) {
            verdict.type = 'error'
          }
          data = verdict
        }
      } catch (e) {
        this.$noty.error('Could not parse response from webWork. Please contact Support.')
        return
      }
      console.error('Route name')
      console.error(data)
      console.error(routeName)
      switch (routeName) {
        case ('questions.view'):
          await vm.showResponse(data)
          break
        case ('instructors.learning_trees.editor'):
          await vm.getAssignmentNodeQuestionToView(question.id.toString())
          break
        default:
          alert(`This route is not set up for processReceiveMessage: ${routeName}`)
          return false
      }
    }
    if ([2, 3, 5].includes(vm.user.role) && clientSideSubmit) {
      let submissionData = {
        'question_id': question.id,
        'submission': event.data,
        'assignment_id': vm.assignmentId,
        'technology': technology,
        'max_score': vm.maxScore,
        'is_h5p_activity_set': vm.ish5pActivitySet
      }
      console.error(submissionData)
      if (isLearningTreeNode) {
        submissionData.learning_tree_id = vm.learningTreeId
        submissionData.is_learning_tree_node = isLearningTreeNode
      }
      // if incorrect, show the learning tree stuff...
      try {
        console.log('about to hide response')
        console.log(routeName)
        if (routeName === 'questions.view') {
          vm.hideResponse()
        }
        const { data } = await axios.post('/api/submissions', submissionData)
        if (!data.message) {
          data.type = 'error'
          data.message = 'The server did not fully respond to this request and your submission may not have been saved.  Please refresh the page to verify the submission and contact support if the problem persists.'
        }

        await vm.showResponse(data)
      } catch (error) {
        error.type = 'error'
        error.message = `The following error occurred: ${error}. Please refresh the page and try again and contact us if the problem persists.`
        await vm.showResponse(error)
      }
    }
  }
}
