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
        NAMESPACE             = "default"             // Kubernetes namespace
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
                        env.KUBERNETES_CREDENTIALS_ID = "reports-production"
                        env.IMAGE_NAME = "anrs125/sample-private"
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
                        sh """
                            echo ${DOCKER_PASSWORD} | docker login -u ${DOCKER_USER} --password-stdin
                        """
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
                        echo "Also pushed as latest."
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
                                sed -i 's|image: ${IMAGE_NAME}:.*|image: ${IMAGE_NAME}:${rollbackVersion}|' deploy.yaml
                                kubectl apply -f deploy.yaml
                                kubectl rollout status deployment/anrs-n ${NAMESPACE}
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
                            echo "Deploying ${IMAGE_NAME}:${BUILD_VERSION} to ${DEPLOY_ENV} cluster..."

                            sh """
                                sed -i 's|image: ${IMAGE_NAME}:.*|image: ${IMAGE_NAME}:${BUILD_VERSION}|' deploy.yaml
                                kubectl apply -f deploy.yaml
                                kubectl rollout status deployment/anrs -n ${NAMESPACE} || {
                                    echo "⚠️ Deployment failed, rolling back..."
                                    kubectl rollout undo deployment/anrs -n ${NAMESPACE}
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
                if (!params.ROLLBACK) {
                    echo "✅ Saving new stable version: ${BUILD_VERSION}"
                    sh "echo ${BUILD_VERSION} > ${VERSION_FILE}"
                    sh "echo ${BUILD_VERSION} >> ${HISTORY_FILE}"

                    slackSend(
                        channel: '#jenkins-alerts',
                        color: '#36A64F',
                        tokenCredentialId: 'slack-token',
                        message: ":white_check_mark: *Deployment Successful!*\n\n:rocket: *App:* Wafdash\n:earth_asia: *Env:* ${DEPLOY_ENV}\n:link: *Job:* ${env.JOB_NAME} #${env.BUILD_NUMBER}\n:mag: *Build URL:* ${env.BUILD_URL}"
                    )
                }
            }
        }

        failure {
            script {
                echo "🚨 Rolling back to last stable version..."
                def LAST_SUCCESSFUL_VERSION = "1.0.0"
                if (fileExists(HISTORY_FILE)) {
                    def successfulVersions = sh(script: """tac ${HISTORY_FILE} | sed '/^\\\$/d' | sed -n '2p'""", returnStdout: true).trim()
                    LAST_SUCCESSFUL_VERSION = successfulVersions.replaceAll(/-commit.*/, "") ?: STABLE_VERSION
                }

                echo "Rolling back to version: ${LAST_SUCCESSFUL_VERSION}"
                dir('kubernetes') {
                    withKubeConfig(credentialsId: env.KUBERNETES_CREDENTIALS_ID) {
                        sh """
                            sed -i 's|image: ${IMAGE_NAME}:.*|image: ${IMAGE_NAME}:${LAST_SUCCESSFUL_VERSION}|' deploy.yaml
                            kubectl apply -f deploy.yaml
                        """
                    }
                }

                slackSend(
                    channel: '#jenkins-alerts',
                    color: '#FF0000',
                    tokenCredentialId: 'slack-token',
                    message: ":x: *Deployment Failed!*\n\n:warning: *App:* Wafdash\n:earth_asia: *Env:* ${DEPLOY_ENV}\n:link: *Job:* ${env.JOB_NAME} #${env.BUILD_NUMBER}\n:scroll: *Logs:* ${env.BUILD_URL}"
                )
            }
        }

        always {
            echo '🧾 Pipeline completed.'
            emailext(
                attachLog: true,
                subject: "'${currentBuild.result}'",
                body: "Project: ${env.JOB_NAME}<br/>Build Number: ${env.BUILD_NUMBER}<br/>URL: ${env.BUILD_URL}<br/>",
                to: 'infra.alerts@prophazecom',
                attachmentsPattern: 'trivyfs.txt,trivyimage.txt'
            )
            cleanWs()
        }
    }
}