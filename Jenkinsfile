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
            choices: ['staging'],
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

    triggers {
        githubPush()   // triggers on branch push & tag push
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
                    echo "🔍 Detecting Git event type..."

                    if (env.GIT_BRANCH?.startsWith("refs/tags/")) {
                        env.IS_TAG_BUILD = "true"
                        env.GIT_REF = env.GIT_BRANCH.replace("refs/tags/", "")
                        echo "🏷️ Tag detected: ${env.GIT_REF}"
                    } else {
                        env.IS_TAG_BUILD = "false"
                        env.GIT_REF = params.BRANCH_PARAM
                        echo "🌿 Branch detected: ${env.GIT_REF}"
                    }

                    checkout([
                        $class: 'GitSCM',
                        branches: [[
                            name: env.IS_TAG_BUILD == "true"
                                ? "refs/tags/${env.GIT_REF}"
                                : "*/${env.GIT_REF}"
                        ]],
                        userRemoteConfigs: [[
                            url: env.GIT_REPO,
                            credentialsId: env.GIT_CREDENTIALS_ID
                        ]]
                    ])
                }
            }
        }

        /* ---------------- ENV SELECTION ---------------- */
        stage('Determine Environment') {
            steps {
                script {
                    if (env.IS_TAG_BUILD == "true") {
                        env.DEPLOY_ENV = "production"
                        env.IMAGE_NAME = "anrs125/reports-tesing"
                        env.TAG_TYPE   = "release"
                    } else if (env.GIT_REF == "staging") {
                        env.DEPLOY_ENV = "staging"
                        env.IMAGE_NAME = "anrs125/reports-tesing"
                        env.TAG_TYPE   = "commit"
                    } else {
                        error("❌ Only staging branch or production tags are allowed")
                    }

                    echo """
                    -------------------------
                    Environment : ${env.DEPLOY_ENV}
                    Git Ref     : ${env.GIT_REF}
                    Image Repo  : ${env.IMAGE_NAME}
                    Tag Mode    : ${env.TAG_TYPE}
                    -------------------------
                    """
                }
            }
        }

        /* ---------------- TAG GENERATION ---------------- */
        stage('Generate Docker Tag') {
            steps {
                script {
                    def imageTag = ""

                    if (params.ROLLBACK) {
                        if (!params.TARGET_VERSION?.trim()) {
                            error("Rollback requested but TARGET_VERSION is empty")
                        }
                        imageTag = params.TARGET_VERSION.trim()
                    }
                    else if (env.TAG_TYPE == "commit") {
                        def commitId = sh(
                            script: "git rev-parse --short HEAD",
                            returnStdout: true
                        ).trim()
                        imageTag = "staging-${commitId}"
                    }
                    else if (env.TAG_TYPE == "release") {
                        def tagName = sh(
                            script: "git describe --tags --exact-match HEAD 2>/dev/null || true",
                            returnStdout: true
                        ).trim()

                        if (!tagName) {
                            error("❌ Production build requires a Git tag. Aborting.")
                        }
                        imageTag = tagName
                    }

                    env.IMAGE_TAG = imageTag
                    echo "🚀 Docker Image Tag: ${env.IMAGE_TAG}"
                }
            }
        }

        /* ---------------- DOCKER LOGIN ---------------- */
        stage('Docker Login') {
            when { expression { return !params.ROLLBACK } }
            steps {
                withCredentials([usernamePassword(
                    credentialsId: env.DOCKER_CREDENTIALS_ID,
                    usernameVariable: 'DOCKER_USER',
                    passwordVariable: 'DOCKER_PASS'
                )]) {
                    sh "echo ${DOCKER_PASS} | docker login -u ${DOCKER_USER} --password-stdin"
                }
            }
        }

        /* ---------------- BUILD & PUSH ---------------- */
        stage('Docker Build & Push') {
            when { expression { return !params.ROLLBACK } }
            steps {
                script {
                    sh """
                        docker build --no-cache -t ${env.IMAGE_NAME}:${env.IMAGE_TAG} .
                        docker push ${env.IMAGE_NAME}:${env.IMAGE_TAG}
                        docker logout
                    """
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
                ✅ *Deployment Successful*
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
                message: """
                ❌ *Build Failed*
                *Env:* ${env.DEPLOY_ENV}
                <${env.BUILD_URL}|View Logs>
                """
            )
        }

        always {
            cleanWs()
        }
    }
}
