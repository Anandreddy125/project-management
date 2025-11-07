pipeline {
    agent any

    options {
        disableConcurrentBuilds()
        timestamps()
        timeout(time: 60, unit: 'MINUTES')
    }

    environment {
        GIT_REPO              = "https://github.com/Anandreddy125/project-management.git"
        GIT_CREDENTIALS_ID    = "terra-github"
        DOCKER_CREDENTIALS_ID = "anand-dockerhub"
        NAMESPACE             = "default"
    }

    parameters {
        choice(name: 'BRANCH_PARAM', choices: ['main', 'master'], description: 'Select branch to build manually')
        booleanParam(name: 'ROLLBACK', defaultValue: false, description: 'Rollback to TARGET_VERSION instead of deploy')
        string(name: 'TARGET_VERSION', defaultValue: '', description: 'Target Docker tag for rollback (if enabled)')
    }

    triggers {
        githubPush()
    }

    stages {

        stage('🧹 Clean Workspace') {
            steps { cleanWs() }
        }

        stage('Checkout Code') {
            steps {
                script {
                    def branchName = env.BRANCH_NAME ?: params.BRANCH_PARAM
                    echo "🔄 Checking out branch: ${branchName}"

                    checkout([$class: 'GitSCM',
                        branches: [[name: "*/${branchName}"]],
                        userRemoteConfigs: [[
                            url: env.GIT_REPO,
                            credentialsId: env.GIT_CREDENTIALS_ID
                        ]]
                    ])
                    env.ACTUAL_BRANCH = branchName
                }
            }
        }

        stage('Determine Environment') {
            steps {
                script {
                    if (env.ACTUAL_BRANCH == "main" || env.ACTUAL_BRANCH == "staging") {
                        env.DEPLOY_ENV = "staging"
                        env.KUBERNETES_CREDENTIALS_ID = "testing-k3s"
                        env.IMAGE_NAME = "anrs125/farhan-testing"
                        env.TAG_TYPE   = "commit"
                    } else if (env.ACTUAL_BRANCH == "master") {
                        env.DEPLOY_ENV = "production"
                        env.KUBERNETES_CREDENTIALS_ID = "testing-k3s"
                         env.IMAGE_NAME = "anrs125/farhan-testing"
                        env.TAG_TYPE   = "release"
                    } else {
                        error("Unsupported branch: ${env.ACTUAL_BRANCH}")
                    }

                    echo """
                    🌍 Environment Info
                    ----------------------
                    Branch: ${env.ACTUAL_BRANCH}
                    Deploy: ${env.DEPLOY_ENV}
                    Repo:   ${env.IMAGE_NAME}
                    Mode:   ${env.TAG_TYPE}
                    """
                }
            }
        }

        stage('Generate Docker Tag') {
            steps {
                script {
                    def commitId = sh(script: "git rev-parse HEAD | cut -c1-7", returnStdout: true).trim()
                    def imageTag = ""

                    if (params.ROLLBACK) {
                        if (!params.TARGET_VERSION?.trim()) {
                            error("Rollback requested but no TARGET_VERSION provided.")
                        }
                        imageTag = params.TARGET_VERSION.trim()
                    } else if (env.TAG_TYPE == "commit") {
                        imageTag = "staging-${commitId}"
                    } else {
                        def tagName = sh(script: "git describe --tags --exact-match HEAD 2>/dev/null || true", returnStdout: true).trim()
                        imageTag = tagName ?: "${commitId}"
                    }

                    env.IMAGE_TAG = imageTag
                    echo "🏷️ Final Image Tag: ${env.IMAGE_TAG}"
                }
            }
        }

        stage('Docker Login') {
            steps {
                script {
                    withCredentials([usernamePassword(credentialsId: env.DOCKER_CREDENTIALS_ID,
                        usernameVariable: 'DOCKER_USER', passwordVariable: 'DOCKER_PASSWORD')]) {
                        sh "echo ${DOCKER_PASSWORD} | docker login -u ${DOCKER_USER} --password-stdin"
                    }
                }
            }
        }

        stage('Docker Build & Push') {
            when { expression { return !params.ROLLBACK } }
            steps {
                script {
                    def imageFull = "${env.IMAGE_NAME}:${env.IMAGE_TAG}"
                    echo "🐳 Building Docker image: ${imageFull}"

                    sh """
                        docker build --pull --no-cache -t ${imageFull} .
                        docker push ${imageFull}
                    """

                    if (env.DEPLOY_ENV == "production") {
                        sh """
                            docker tag ${imageFull} ${env.IMAGE_NAME}:latest
                            docker push ${env.IMAGE_NAME}:latest
                        """
                        echo "✅ Also pushed as latest."
                    }

                    sh "docker logout"
                }
            }
        }

        stage('Rollback Version') {
            when { expression { return params.ROLLBACK && params.TARGET_VERSION?.trim() } }
            steps {
                script {
                    def rollbackVersion = params.TARGET_VERSION.trim()
                    echo "Rolling back to version: ${rollbackVersion}"

                    dir('kubernetes') {
                        withKubeConfig(credentialsId: env.KUBERNETES_CREDENTIALS_ID) {
                            sh """
                                sed -i 's|image: ${env.IMAGE_NAME}:.*|image: ${env.IMAGE_NAME}:${rollbackVersion}|' deploy.yaml
                                kubectl apply -f deploy.yaml
                                kubectl rollout status deployment/anrs -n ${env.NAMESPACE}
                            """
                        }
                    }
                }
            }
        }

        stage('🚀 Deploy to Kubernetes') {
            when { expression { return !params.ROLLBACK } }
            steps {
                script {
                    dir('kubernetes') {
                        withKubeConfig(credentialsId: env.KUBERNETES_CREDENTIALS_ID) {
                            echo "Deploying ${env.IMAGE_NAME}:${env.IMAGE_TAG} to ${env.DEPLOY_ENV} cluster..."

                            sh """
                                sed -i 's|image: ${env.IMAGE_NAME}:.*|image: ${env.IMAGE_NAME}:${env.IMAGE_TAG}|' deploy.yaml
                                kubectl apply -f deploy.yaml
                                kubectl rollout status deployment/anrs -n ${env.NAMESPACE} || {
                                    echo "⚠️ Deployment failed, rolling back..."
                                    kubectl rollout undo deployment/anrs -n ${env.NAMESPACE}
                                    exit 1
                                }
                            """
                        }
                    }
                }
            }
        }
    }

    post {
        success {
            script {
                def VERSION_FILE = "version.txt"
                def HISTORY_FILE = "history.txt"

                echo "✅ Saving new stable version: ${env.IMAGE_TAG}"
                sh "echo ${env.IMAGE_TAG} > ${VERSION_FILE}"
                sh "echo ${env.IMAGE_TAG} >> ${HISTORY_FILE}"

                slackSend(
                    channel: '#jenkins-alerts',
                    color: '#36A64F',
                    tokenCredentialId: 'slack-token',
                    message: ":white_check_mark: *Deployment Successful!*\n\n*App:* Project Management\n*Env:* ${env.DEPLOY_ENV}\n*Image:* ${env.IMAGE_NAME}:${env.IMAGE_TAG}\n<${env.BUILD_URL}|View Build>"
                )
            }
        }

        failure {
            script {
                def VERSION_FILE = "version.txt"
                def HISTORY_FILE = "history.txt"
                def LAST_SUCCESSFUL_VERSION = "latest"

                if (fileExists(HISTORY_FILE)) {
                    def previous = sh(script: "tac ${HISTORY_FILE} | sed -n '2p'", returnStdout: true).trim()
                    if (previous) { LAST_SUCCESSFUL_VERSION = previous }
                }

                echo "🚨 Rolling back to last stable version: ${LAST_SUCCESSFUL_VERSION}"
                dir('kubernetes') {
                    withKubeConfig(credentialsId: env.KUBERNETES_CREDENTIALS_ID) {
                        sh """
                            sed -i 's|image: ${env.IMAGE_NAME}:.*|image: ${env.IMAGE_NAME}:${LAST_SUCCESSFUL_VERSION}|' deploy.yaml
                            kubectl apply -f deploy.yaml
                        """
                    }
                }

                slackSend(
                    channel: '#jenkins-alerts',
                    color: '#FF0000',
                    tokenCredentialId: 'slack-token',
                    message: ":x: *Deployment Failed!*\n\n*App:* Project Management\n*Env:* ${env.DEPLOY_ENV}\n*Rolled back to:* ${LAST_SUCCESSFUL_VERSION}\n<${env.BUILD_URL}|View Logs>"
                )
            }
        }

        always {
            echo '🧾 Pipeline completed.'
            emailext(
                attachLog: true,
                subject: "Jenkins Pipeline - ${currentBuild.result}",
                body: """
                    <b>Project:</b> ${env.JOB_NAME}<br/>
                    <b>Build Number:</b> ${env.BUILD_NUMBER}<br/>
                    <b>Status:</b> ${currentBuild.result}<br/>
                    <b>Image:</b> ${env.IMAGE_NAME}:${env.IMAGE_TAG}<br/>
                    <b>URL:</b> <a href="${env.BUILD_URL}">${env.BUILD_URL}</a>
                """,
                to: 'infra.alerts@prophaze.com'
            )
            cleanWs()
        }
    }
}
