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

        stage('Clean Workspace') {
            steps { cleanWs() }
        }

        stage('Checkout Code') {
            steps {
                script {
                    def branchName = env.BRANCH_NAME ?: params.BRANCH_PARAM
                    echo "🔹 Checking out branch: ${branchName}"

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
                    if (env.ACTUAL_BRANCH == "main" || env.ACTUAL_BRANCH == "main") {
                        env.DEPLOY_ENV = "main"
                        env.IMAGE_NAME = "anrs125/sample-private"
                        env.KUBERNETES_CREDENTIALS_ID = "reports-staging1"
                        env.DEPLOYMENT_FILE = "staging-report.yaml"
                        env.DEPLOYMENT_NAME = "staging-reports-api"
                        env.TAG_TYPE = "commit"
                    } else if (env.ACTUAL_BRANCH == "master") {
                        env.DEPLOY_ENV = "production"
                        env.IMAGE_NAME = "anrs125/sample-private"
                        env.KUBERNETES_CREDENTIALS_ID = "k3s-report-staging1" // TODO: change credential name BAD NAMING
                        env.DEPLOYMENT_FILE = "prod-reports.yaml"
                        env.DEPLOYMENT_NAME = "prod-reports-api"
                        env.TAG_TYPE = "release"
                    } else {
                        error("Unsupported branch: ${env.ACTUAL_BRANCH}")
                    }

                    echo """
                    Environment Info
                    ----------------------
                    Branch: ${env.ACTUAL_BRANCH}
                    Deploy: ${env.DEPLOY_ENV}
                    Repo:   ${env.IMAGE_NAME}
                    Mode:   ${env.TAG_TYPE}
                    Namespace: ${env.NAMESPACE}
                    Deployment File: ${env.DEPLOYMENT_FILE}
                    """
                }
            }
        }

        stage('Trivy Filesystem Scan') {
            steps {
                script {
                    echo "Running Trivy filesystem scan..."
                    sh "trivy fs . --severity HIGH,CRITICAL > trivyfs.txt || true"
                    echo "Filesystem scan completed — saved in trivyfs.txt"
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
                        imageTag = tagName ?: "v${commitId}"
                    }

                    env.IMAGE_TAG = imageTag
                    echo "Final Docker Image Tag: ${env.IMAGE_TAG}"
                }
            }
        }

        stage('🔐 Docker Login') {
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
                }
            }
        }
    }
}