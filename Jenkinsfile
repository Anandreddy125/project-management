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
                    echo "Checking out branch: ${branchName}"

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
                        env.KUBERNETES_CREDENTIALS_ID = "reports-staging"
                        env.IMAGE_NAME = "anrs125/farhan-testing"
                        env.TAG_TYPE   = "commit"
                         env.NAMESPACE = "reports"
                    } else if (env.ACTUAL_BRANCH == "master") {
                        env.DEPLOY_ENV = "production"
                        env.IMAGE_NAME = "anrs125/sample-private"
                        env.KUBERNETES_CREDENTIALS_ID = "reports-production"
                        env.TAG_TYPE   = "release"
                    } else {
                        error("Unsupported branch: ${env.ACTUAL_BRANCH}")
                    }

                    echo """
                      Environment Info
                    ------------------
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
                    echo "Building Docker image: ${imageFull}"

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
        stage('⏪ Rollback to Previous Version') {
            when { expression { return params.ROLLBACK && params.TARGET_VERSION?.trim() } }
            steps {
                script {
                    echo "⚙️ Initiating rollback to: ${params.TARGET_VERSION}"
                    withKubeConfig(credentialsId: env.KUBERNETES_CREDENTIALS_ID) {
                        sh """
                            sed -i 's|image: ${env.IMAGE_NAME}:.*|image: ${env.IMAGE_NAME}:${params.TARGET_VERSION}|' kubetentes/deploy.yaml
                            kubectl apply -f jenkins/deploy.yaml -n ${env.NAMESPACE}
                            kubectl rollout status deployment/reports-api -n ${env.NAMESPACE}
                        """
                    }
                }
            }
        }

        stage('Deploy to Kubernetes') {
            when { expression { return !params.ROLLBACK } }
            steps {
                script {
                    withKubeConfig(credentialsId: env.KUBERNETES_CREDENTIALS_ID) {
                        echo "Deploying ${env.IMAGE_NAME}:${env.IMAGE_TAG} to ${env.DEPLOY_ENV} cluster..."
                        sh """
                            sed -i 's|image: ${env.IMAGE_NAME}:.*|image: ${env.IMAGE_NAME}:${env.IMAGE_TAG}|' kubernetes/deploy.yaml
                            kubectl apply -f jenkins/deploy.yaml -n ${env.NAMESPACE}
                            kubectl rollout status deployment/reports-api -n ${env.NAMESPACE} || {
                                echo "⚠️ Deployment failed, rolling back..."
                                kubectl rollout undo deployment/reports-api -n ${env.NAMESPACE}
                                exit 1
                            }
                        """
                    }
                }
            }
        }
    }

    post {
        success {
            echo """
              Build & Deploy Successful!
            ------------------------------
                Environment: ${env.DEPLOY_ENV}
                Image: ${env.IMAGE_NAME}:${env.IMAGE_TAG}
                Build Number: ${env.BUILD_NUMBER}
            """
        }
        failure {
            echo "Build or Deployment Failed! Please check logs."
        }
        always {
            cleanWs()
        }
    }
}
