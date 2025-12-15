pipeline {
    agent any

    options {
        disableConcurrentBuilds()
        timestamps()
        timeout(time: 60, unit: 'MINUTES')
        skipDefaultCheckout(true)
    }

    environment {
        GIT_REPO              = "https://github.com/Anandreddy125/project-management.git"
        GIT_CREDENTIALS_ID    = "terra-github"
        DOCKER_CREDENTIALS_ID = "anand-dockerhub"
    }

    parameters {
        choice(
            name: 'BRANCH_PARAM',
            choices: ['staging', 'master'],
            description: 'Manual build branch (tags auto-detect)'
        )
        booleanParam(
            name: 'ROLLBACK',
            defaultValue: false,
            description: 'Rollback using TARGET_VERSION'
        )
        string(
            name: 'TARGET_VERSION',
            defaultValue: '',
            description: 'Docker tag for rollback'
        )
    }

    stages {

        /* ---------------- CLEAN ---------------- */
        stage('Clean Workspace') {
            steps { cleanWs() }
        }

        /* ---------------- CHECKOUT ---------------- */
        stage('Checkout Code') {
            steps {
                script {
                    def ref = env.GIT_BRANCH ?: params.BRANCH_PARAM

                    checkout([$class: 'GitSCM',
                        branches: [[name: ref.startsWith('refs/tags/')
                            ? ref
                            : "*/${ref}"]],
                        userRemoteConfigs: [[
                            url: env.GIT_REPO,
                            credentialsId: env.GIT_CREDENTIALS_ID
                        ]]
                    ])

                    env.GIT_REF = ref
                    echo "Checked out ref: ${env.GIT_REF}"
                }
            }
        }

        /* ---------------- HARD BLOCK MASTER PUSH ---------------- */
        stage('Pre-check Trigger') {
            steps {
                script {
                    // If master branch build WITHOUT tag → STOP
                    if (env.GIT_REF == 'master' && !env.GIT_BRANCH?.startsWith('refs/tags/')) {
                        error("❌ Master branch push detected. Production builds are allowed ONLY via Git tags.")
                    }
                }
            }
        }

        /* ---------------- DETERMINE ENV ---------------- */
        stage('Determine Environment') {
            steps {
                script {

                    // TAG BUILD → PRODUCTION
                    if (env.GIT_BRANCH?.startsWith("refs/tags/")) {
                        env.ACTUAL_BRANCH = "master"
                        env.DEPLOY_ENV    = "production"
                        env.IMAGE_NAME    = "prophazedocker/i-report"
                        env.TAG_TYPE      = "release"
                    }

                    // STAGING
                    else if (env.GIT_REF == "staging") {
                        env.ACTUAL_BRANCH = "staging"
                        env.DEPLOY_ENV    = "staging"
                        env.IMAGE_NAME    = "prophazedocker/staging-report"
                        env.TAG_TYPE      = "commit"
                    }

                    else {
                        error("❌ Unsupported ref: ${env.GIT_REF}")
                    }

                    echo """
                    -------------------------------
                    Branch   : ${env.ACTUAL_BRANCH}
                    Deploy   : ${env.DEPLOY_ENV}
                    Image    : ${env.IMAGE_NAME}
                    Tag Type : ${env.TAG_TYPE}
                    -------------------------------
                    """
                }
            }
        }

        /* ---------------- GENERATE DOCKER TAG ---------------- */
        stage('Generate Docker Tag') {
            steps {
                script {
                    if (params.ROLLBACK) {
                        if (!params.TARGET_VERSION?.trim()) {
                            error("Rollback enabled but TARGET_VERSION is empty")
                        }
                        env.IMAGE_TAG = params.TARGET_VERSION.trim()
                    }

                    else if (env.TAG_TYPE == "commit") {
                        def commitId = sh(
                            script: "git rev-parse --short HEAD",
                            returnStdout: true
                        ).trim()
                        env.IMAGE_TAG = "staging-${commitId}"
                    }

                    else if (env.TAG_TYPE == "release") {
                        def tagName = sh(
                            script: "git describe --tags --exact-match HEAD",
                            returnStdout: true
                        ).trim()
                        env.IMAGE_TAG = tagName
                    }

                    echo "🚀 FINAL IMAGE TAG: ${env.IMAGE_TAG}"
                }
            }
        }

        /* ---------------- DOCKER BUILD & PUSH ---------------- */
        stage('Docker Build & Push') {
            when { expression { !params.ROLLBACK } }
            steps {
                script {
                    def imageFull = "${env.IMAGE_NAME}:${env.IMAGE_TAG}"

                    withCredentials([
                        usernamePassword(
                            credentialsId: env.DOCKER_CREDENTIALS_ID,
                            usernameVariable: 'DOCKER_USER',
                            passwordVariable: 'DOCKER_PASSWORD'
                        )
                    ]) {
                        sh """
                          echo \$DOCKER_PASSWORD | docker login -u \$DOCKER_USER --password-stdin
                          docker build -t ${imageFull} .
                          docker push ${imageFull}
                          docker logout
                        """
                    }
                }
            }
        }
    }

    post {
        success {
            slackSend(
                channel: 'C09M08HUK8W',
                color: '#36A64F',
                tokenCredentialId: 'slack-token',
                message: """
:white_check_mark: *Deployment Successful*
*Env:* ${env.DEPLOY_ENV}
*Image:* ${env.IMAGE_NAME}:${env.IMAGE_TAG}
<${env.BUILD_URL}|View Build>
"""
            )
        }

        failure {
            slackSend(
                channel: 'C09M08HUK8W',
                color: '#FF0000',
                tokenCredentialId: 'slack-token',
                message: ":x: *Build Failed* <${env.BUILD_URL}|View Logs>"
            )
        }

        always {
            cleanWs()
        }
    }
}
// change happen on jenkinsfile 