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

    triggers {
        githubPush()
    }

    stages {

        /* CLEAN */
        stage('Clean Workspace') {
            steps { cleanWs() }
        }

        /* CHECKOUT (supports main, master, tags) */
        stage('Checkout Code') {
            steps {
                script {
                    echo "🔹 Checking out branches + tags..."

                    checkout([$class: 'GitSCM',
                        branches: [[name: "**"]],
                        userRemoteConfigs: [[
                            url: env.GIT_REPO,
                            credentialsId: env.GIT_CREDENTIALS_ID
                        ]],
                        extensions: [
                            [$class: 'CloneOption', shallow: false, noTags: false]
                        ]
                    ])

                    /* Detect branch or tag */
                    def branchRef = sh(script: "git symbolic-ref -q HEAD || true", returnStdout: true).trim()
                    def tagRef    = sh(script: "git describe --tags --exact-match HEAD 2>/dev/null || true",
                                       returnStdout: true).trim()

                    if (branchRef.startsWith("refs/heads/")) {
                        env.ACTUAL_BRANCH = branchRef.replace("refs/heads/", "")
                        env.GIT_TAG = ""
                        echo "✔ Branch detected: ${env.ACTUAL_BRANCH}"

                    } else if (tagRef) {
                        env.GIT_TAG = tagRef
                        env.ACTUAL_BRANCH = "master"  // production tags only
                        echo "✔ Tag detected: ${env.GIT_TAG}"

                    } else {
                        echo "⛔ Not a valid branch or tag build. Stopping."
                        currentBuild.result = "NOT_BUILT"
                        return
                    }
                }
            }
        }

        /* DETERMINE ENVIRONMENT */
        stage('Determine Environment') {
            steps {
                script {
                    if (env.ACTUAL_BRANCH == "main") {
                        /* STAGING BUILD */
                        env.DEPLOY_ENV = "staging"
                        env.IMAGE_NAME = "anrs125/farhan-testing"
                        env.TAG_TYPE   = "commit"

                    } else if (env.GIT_TAG) {
                        /* PRODUCTION TAG BUILD */
                        env.DEPLOY_ENV = "production"
                        env.IMAGE_NAME = "anrs125/sample-private"
                        env.TAG_TYPE   = "release"

                    } else {
                        echo "⛔ Skipping — not staging or production-tag build."
                        currentBuild.result = "NOT_BUILT"
                        return
                    }

                    echo """
                    ========== BUILD CONFIG ==========
                    Branch:     ${env.ACTUAL_BRANCH}
                    Tag:        ${env.GIT_TAG ?: 'none'}
                    Env:        ${env.DEPLOY_ENV}
                    Repo:       ${env.IMAGE_NAME}
                    Tag Type:   ${env.TAG_TYPE}
                    =================================
                    """
                }
            }
        }

        /* TRIVY FS SCAN */
        stage('Trivy Filesystem Scan') {
            when { expression { return env.DEPLOY_ENV != null } }
            steps {
                sh "trivy fs . --severity HIGH,CRITICAL > trivyfs.txt || true"
            }
        }

        /* GENERATE DOCKER TAG */
        stage('Generate Docker Tag') {
            when { expression { return env.DEPLOY_ENV != null } }
            steps {
                script {
                    def commitId = sh(script: "git rev-parse --short HEAD", returnStdout: true).trim()

                    if (params.ROLLBACK) {
                        env.IMAGE_TAG = params.TARGET_VERSION.trim()

                    } else if (env.TAG_TYPE == "commit") {
                        env.IMAGE_TAG = "staging-${commitId}"

                    } else if (env.TAG_TYPE == "release") {
                        env.IMAGE_TAG = env.GIT_TAG
                    }

                    echo "✔ Final Docker Image Tag: ${env.IMAGE_TAG}"
                }
            }
        }

        /* DOCKER LOGIN */
        stage('Docker Login') {
            when { expression { return env.DEPLOY_ENV != null } }
            steps {
                withCredentials([usernamePassword(
                    credentialsId: env.DOCKER_CREDENTIALS_ID,
                    usernameVariable: 'DOCKER_USER',
                    passwordVariable: 'DOCKER_PASSWORD'
                )]) {
                    sh "echo ${DOCKER_PASSWORD} | docker login -u ${DOCKER_USER} --password-stdin"
                }
            }
        }

        /* DOCKER BUILD & PUSH */
        stage('Docker Build & Push') {
            when { expression { return env.DEPLOY_ENV != null && !params.ROLLBACK } }
            steps {
                script {
                    def img = "${env.IMAGE_NAME}:${env.IMAGE_TAG}"

                    sh """
                        docker build --no-cache -t ${img} .
                        docker push ${img}
                    """
                }
            }
        }

        /* DOCKER TRIVY SCAN */
        stage('Trivy Image Scan') {
            when { expression { return env.DEPLOY_ENV != null && !params.ROLLBACK } }
            steps {
                sh """
                    trivy image ${env.IMAGE_NAME}:${env.IMAGE_TAG} \
                    --severity HIGH,CRITICAL > trivyimage.txt || true
                """
            }
        }
    }
}
