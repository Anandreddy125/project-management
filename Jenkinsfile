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
        IMAGE_REPO            = "anrs125/reports-tesing"
    }

    parameters {
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
        githubPush()
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

                    /*
                      Multibranch behavior:
                      - Branch job: BRANCH_NAME = staging | master
                      - Tag job   : TAG_NAME    = v1.0.9
                    */

                    if (env.TAG_NAME) {

                        env.IS_TAG_BUILD   = "true"
                        env.ACTUAL_BRANCH = "master"
                        env.BUILD_TAG     = env.TAG_NAME

                        echo "🏷️ Tag job detected: ${env.BUILD_TAG}"

                        checkout([
                            $class: 'GitSCM',
                            branches: [[name: "refs/tags/${env.BUILD_TAG}"]],
                            userRemoteConfigs: [[
                                url: env.GIT_REPO,
                                credentialsId: env.GIT_CREDENTIALS_ID
                            ]]
                        ])

                    } else {

                        env.IS_TAG_BUILD   = "false"
                        env.ACTUAL_BRANCH = env.BRANCH_NAME

                        echo "🔄 Branch job detected: ${env.ACTUAL_BRANCH}"

                        checkout([
                            $class: 'GitSCM',
                            branches: [[name: "*/${env.ACTUAL_BRANCH}"]],
                            userRemoteConfigs: [[
                                url: env.GIT_REPO,
                                credentialsId: env.GIT_CREDENTIALS_ID
                            ]]
                        ])
                    }
                }
            }
        }

        /* ---------------- ENV ---------------- */
        stage('Determine Environment') {
            steps {
                script {

                    if (env.IS_TAG_BUILD == "true") {

                        env.DEPLOY_ENV = "production"
                        env.TAG_TYPE   = "release"

                    } else if (env.ACTUAL_BRANCH == "staging") {

                        env.DEPLOY_ENV = "staging"
                        env.TAG_TYPE   = "commit"

                    } else if (env.ACTUAL_BRANCH == "master") {

                        error("❌ Direct master branch builds are blocked. Use Git tags for production.")

                    } else {
                        error("Unsupported branch: ${env.ACTUAL_BRANCH}")
                    }

                    echo """
==============================
Environment Info
==============================
Branch     : ${env.ACTUAL_BRANCH}
Deploy Env : ${env.DEPLOY_ENV}
Tag Type   : ${env.TAG_TYPE}
Tag Build  : ${env.IS_TAG_BUILD}
Tag Name   : ${env.BUILD_TAG ?: 'N/A'}
==============================
"""
                }
            }
        }

        /* ---------------- DOCKER TAG ---------------- */
        stage('Generate Docker Tag') {
            steps {
                script {

                    if (params.ROLLBACK) {

                        if (!params.TARGET_VERSION?.trim()) {
                            error("Rollback requested but TARGET_VERSION missing")
                        }
                        env.IMAGE_TAG = params.TARGET_VERSION.trim()

                    } else if (env.IS_TAG_BUILD == "true") {

                        env.IMAGE_TAG = env.BUILD_TAG

                    } else {

                        def commitId = sh(
                            script: "git rev-parse --short HEAD",
                            returnStdout: true
                        ).trim()

                        env.IMAGE_TAG = "staging-${commitId}"
                    }

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
                    def image = "${env.IMAGE_REPO}:${env.IMAGE_TAG}"
                    sh """
                        docker build --no-cache -t ${image} .
                        docker push ${image}
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
:white_check_mark: *Deployment Successful*
Env   : ${env.DEPLOY_ENV}
Image : ${env.IMAGE_REPO}:${env.IMAGE_TAG}
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
