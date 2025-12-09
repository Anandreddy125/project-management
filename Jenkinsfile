pipeline {
    agent any

    options {
        disableConcurrentBuilds()
        timestamps()
        timeout(time: 60, unit: 'MINUTES')
    }

    environment {
        SCANNER_HOME          = tool('sonar-scanner')
        GIT_REPO              = "https://github.com/Anandreddy125/project-management.git"
        GIT_CREDENTIALS_ID    = "terra-github"
        DOCKER_CREDENTIALS_ID = "anand-dockerhub"
    }

    parameters {
        choice(name: 'BRANCH_PARAM', choices: ['staging', 'main', 'master'], description: 'Select branch to build manually')
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
                        ]],
                        extensions: [
                            [$class: 'CloneOption', noTags: false, depth: 0, shallow: false],
                            [$class: 'CheckoutOption', timeout: 30],
                            [$class: 'CleanBeforeCheckout'],
                            [$class: 'PruneStaleBranch'],
                            [$class: 'FetchTags']
                        ]
                    ])

                    // 🔥 Force fetch ALL latest tags from GitHub (fixes old tag issue)
                    sh """
                        git fetch --tags --force
                        echo "🔍 Available Git Tags:"
                        git tag -l
                    """

                    env.ACTUAL_BRANCH = branchName
                }
            }
        }

        stage('Determine Environment') {
            steps {
                script {
                    if (env.ACTUAL_BRANCH == "staging") {
                        env.DEPLOY_ENV = "staging"
                        env.IMAGE_NAME = "anrs125/sample-private"
                        env.KUBERNETES_CREDENTIALS_ID = "reports-staging1"
                        env.DEPLOYMENT_FILE = "staging-report.yaml"
                        env.DEPLOYMENT_NAME = "staging-reports-api"
                        env.TAG_TYPE = "commit"

                    } else if (env.ACTUAL_BRANCH == "master") {
                        env.DEPLOY_ENV = "production"
                        env.IMAGE_NAME = "anrs125/farhan-testing"
                        env.KUBERNETES_CREDENTIALS_ID = "k3s-report-staging1"
                        env.DEPLOYMENT_FILE = "prod-reports.yaml"
                        env.DEPLOYMENT_NAME = "prod-reports-api"
                        env.TAG_TYPE = "release"

                    } else {
                        error("Unsupported branch: ${env.ACTUAL_BRANCH}")
                    }

                    echo """
                    Environment Info
                    -----------------------
                    Branch:          ${env.ACTUAL_BRANCH}
                    Deployment Env:  ${env.DEPLOY_ENV}
                    Docker Repo:     ${env.IMAGE_NAME}
                    Tag Type:        ${env.TAG_TYPE}
                    Deployment File: ${env.DEPLOYMENT_FILE}
                    """
                }
            }
        }

        stage('Trivy Filesystem Scan') {
            steps {
                sh "trivy fs . --severity HIGH,CRITICAL > trivyfs.txt || true"
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
                    }

                    else if (env.TAG_TYPE == "commit") {
                        // STAGING VERSION
                        imageTag = "staging-${commitId}"
                    }

                    else if (env.TAG_TYPE == "release") {
                        // PRODUCTION VERSION MUST USE GIT TAG
                        def tagName = sh(
                            script: "git describe --tags --exact-match HEAD 2>/dev/null || true",
                            returnStdout: true
                        ).trim()

                        if (!tagName) {
                            error("""
                            ❌ PRODUCTION BUILD BLOCKED
                            ------------------------------------
                            No Git tag found on this commit.
                            Production deployments MUST use a version tag like:
                                v1.0.0
                                v2.3.1
                                v5.6.7

                            FIX:
                            git tag v1.0.0
                            git push origin v1.0.0
                            ------------------------------------
                            """)
                        }

                        imageTag = tagName
                    }

                    env.IMAGE_TAG = imageTag
                    echo "✔ Final Docker Image Tag: ${env.IMAGE_TAG}"
                }
            }
        }

        stage('Docker Login') {
            steps {
                withCredentials([usernamePassword(credentialsId: env.DOCKER_CREDENTIALS_ID,
                    usernameVariable: 'DOCKER_USER', passwordVariable: 'DOCKER_PASSWORD')]) {
                    sh "echo ${DOCKER_PASSWORD} | docker login -u ${DOCKER_USER} --password-stdin"
                }
            }
        }

        stage('Docker Build & Push') {
            when { expression { !params.ROLLBACK } }
            steps {
                script {
                    def imageFull = "${env.IMAGE_NAME}:${env.IMAGE_TAG}"

                    sh """
                        docker build --pull --no-cache -t ${imageFull} .
                        docker push ${imageFull}
                    """
                }
            }
        }

        stage('Trivy Image Scan') {
            when { expression { !params.ROLLBACK } }
            steps {
                sh """
                    docker pull ${env.IMAGE_NAME}:${env.IMAGE_TAG} || true
                    trivy image ${env.IMAGE_NAME}:${env.IMAGE_TAG} --severity HIGH,CRITICAL > trivyimage.txt || true
                """
            }
        }
    }
}
