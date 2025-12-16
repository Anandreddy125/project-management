pipeline {
    agent any

    options {
        disableConcurrentBuilds()
        timestamps()
        timeout(time: 60, unit: 'MINUTES')
    }

    environment {
        GIT_REPO           = "https://github.com/Anandreddy125/project-management.git"
        GIT_CREDENTIALS_ID = "terra-github"
        DOCKER_CREDENTIALS_ID = "anand-dockerhub"
    }

    /* ONE trigger is enough */
    triggers {
        githubPush()
    }

    stages {

        /* ---------------- CLEAN ---------------- */
        stage('Clean Workspace') {
            steps { cleanWs() }
        }

        /* ---------------- CHECKOUT (RAW) ---------------- */
        stage('Checkout Code') {
            steps {
                checkout([
                    $class: 'GitSCM',
                    branches: [[name: '**']],
                    userRemoteConfigs: [[
                        url: env.GIT_REPO,
                        credentialsId: env.GIT_CREDENTIALS_ID
                    ]]
                ])
            }
        }

        /* ---------------- DETECT ENV (SINGLE SOURCE OF TRUTH) ---------------- */
        stage('Detect Environment') {
            steps {
                script {

                    // Detect tag (production)
                    def tag = sh(
                        script: "git describe --tags --exact-match 2>/dev/null || true",
                        returnStdout: true
                    ).trim()

                    // Detect branch (staging)
                    def branch = sh(
                        script: "git branch --show-current",
                        returnStdout: true
                    ).trim()

                    echo "Detected branch: ${branch ?: 'DETACHED'}"
                    echo "Detected tag   : ${tag ?: 'none'}"

                    if (tag) {
                        /* ---------------- PRODUCTION ---------------- */
                        env.DEPLOY_ENV = "production"
                        env.IMAGE_NAME = "anrs125/reports-tesing"
                        env.IMAGE_TAG  = tag
                        env.IS_PROD    = "true"

                        echo "🚀 Production release detected via tag: ${tag}"

                    } else if (branch == "staging") {
                        /* ---------------- STAGING ---------------- */
                        env.DEPLOY_ENV = "staging"
                        env.IMAGE_NAME = "anrs125/reports-tesing"

                        def commit = sh(
                            script: "git rev-parse --short HEAD",
                            returnStdout: true
                        ).trim()

                        env.IMAGE_TAG = "staging-${commit}"
                        env.IS_PROD   = "false"

                        echo "🧪 Staging build detected"

                    } else {
                        error("""
❌ Build blocked!

Allowed triggers:
 - git push origin staging
 - git push origin <tag>

Detected:
 - branch: ${branch}
 - tag   : ${tag ?: 'none'}
""")
                    }
                }
            }
        }

        /* ---------------- DOCKER BUILD & PUSH ---------------- */
        stage('Docker Build & Push') {
            when { expression { return !params.ROLLBACK } }
            steps {
                script {
                    def imageFull = "${env.IMAGE_NAME}:${env.IMAGE_TAG}"
                    echo "Building image: ${imageFull}"

                    withCredentials([usernamePassword(
                        credentialsId: env.DOCKER_CREDENTIALS_ID,
                        usernameVariable: 'DOCKER_USER',
                        passwordVariable: 'DOCKER_PASS'
                    )]) {
                        sh """
                            echo \$DOCKER_PASS | docker login -u \$DOCKER_USER --password-stdin
                            docker build -t ${imageFull} .
                            docker push ${imageFull}
                            docker logout
                        """
                    }
                }
            }
        }

        /* ---------------- DEPLOY ---------------- */
        stage('Deploy') {
            steps {
                script {
                    echo "🚀 Deploying ${env.IMAGE_NAME}:${env.IMAGE_TAG} to ${env.DEPLOY_ENV}"
                    // kubectl apply / helm upgrade goes here
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
Image : ${env.IMAGE_NAME}:${env.IMAGE_TAG}
<${env.BUILD_URL}|View Build>
"""
            )
        }

        failure {
            slackSend(
                channel: 'C09M08HUK8W',
                color: '#FF0000',
                tokenCredentialId: 'slack-token',
                message: ":x: *Deployment Failed* <${env.BUILD_URL}|View Logs>"
            )
        }

        always {
            cleanWs()
        }
    }
}
