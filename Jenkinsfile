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
        booleanParam(name: 'ROLLBACK', defaultValue: false, description: 'Rollback to TARGET_VERSION?')
        string(name: 'TARGET_VERSION', defaultValue: '', description: 'Rollback version')
    }

    triggers {
        githubPush()   // Webhook-based trigger (branch + tag)
    }

    stages {

        /* --------------------------- CLEAN ---------------------------- */
        stage('Clean Workspace') {
            steps { cleanWs() }
        }

        /* ------------------------ CHECKOUT ---------------------------- */
        stage('Checkout Code') {
            steps {
                script {
                    echo "🔹 Checking out main / master / tag"

                    checkout([$class: 'GitSCM',
                        branches: [[name: "**"]],
                        userRemoteConfigs: [[
                            url: env.GIT_REPO,
                            credentialsId: env.GIT_CREDENTIALS_ID
                        ]],
                        extensions: [
                            [$class: "CloneOption", shallow: false, noTags: false]
                        ]
                    ])

                    /* ---- Detect Branch or Tag ---- */
                    def ref = sh(script: "git symbolic-ref -q HEAD || true", returnStdout: true).trim()

                    if (ref.startsWith("refs/heads/")) {

                        // Normal branch build (main or master)
                        env.ACTUAL_BRANCH = ref.replace("refs/heads/", "")
                        echo "✔ Branch detected: ${env.ACTUAL_BRANCH}"

                    } else {

                        // Try to detect tag
                        def tag = sh(script: "git describe --tags --exact-match HEAD 2>/dev/null || true",
                                     returnStdout: true).trim()

                        if (tag) {
                            env.GIT_TAG = tag
                            env.ACTUAL_BRANCH = "master"  // Production tags always considered master
                            echo "✔ Tag detected: ${env.GIT_TAG}"

                        } else {
                            echo "⛔ Not a valid branch or tag build. Skipping pipeline."
                            currentBuild.result = "NOT_BUILT"
                            return
                        }
                    }
                }
            }
        }

        /* -------------------- DETERMINE ENVIRONMENT -------------------- */
        stage('Determine Environment') {
            steps {
                script {

                    if (env.ACTUAL_BRANCH == "main") {
                        // STAGING
                        env.DEPLOY_ENV = "staging"
                        env.IMAGE_NAME = "anrs125/sample-private"
                        env.TAG_TYPE   = "commit"

                    } else if (env.GIT_TAG && env.ACTUAL_BRANCH == "master") {
                        // PRODUCTION
                        env.DEPLOY_ENV = "production"
                        env.IMAGE_NAME = "anrs125/sample-private"
                        env.TAG_TYPE   = "release"

                    } else {
                        echo "⛔ Not staging or production-tag build. Stopping."
                        currentBuild.result = "NOT_BUILT"
                        return
                    }

                    echo """
                    ===============================
                     DEPLOYMENT CONFIGURATION
                    ===============================
                    Git Branch:        ${env.ACTUAL_BRANCH}
                    Git Tag:           ${env.GIT_TAG ?: "N/A"}
                    Deployment Env:    ${env.DEPLOY_ENV}
                    Docker Image Repo: ${env.IMAGE_NAME}
                    Tag Mode:          ${env.TAG_TYPE}
                    ===============================
                    """
                }
            }
        }

        /* ------------------- TRIVY SCAN (FILESYSTEM) ------------------- */
        stage('Trivy Filesystem Scan') {
            when { expression { return env.DEPLOY_ENV != null } }
            steps {
                sh "trivy fs . --severity HIGH,CRITICAL > trivyfs.txt || true"
            }
        }

        /* --------------------- GENERATE DOCKER TAG ---------------------- */
        stage('Generate Docker Tag') {
            when { expression { return env.DEPLOY_ENV != null } }
            steps {
                script {

                    def commitId = sh(script: "git rev-parse --short HEAD", returnStdout: true).trim()

                    if (params.ROLLBACK) {
                        env.IMAGE_TAG = params.TARGET_VERSION.trim()

                    } else if (env.TAG_TYPE == "commit") {
                        /* Staging */
                        env.IMAGE_TAG = "staging-${commitId}"

                    } else if (env.TAG_TYPE == "release") {
                        /* Production */
                        env.IMAGE_TAG = env.GIT_TAG
                    }

                    echo "✔ Final Docker Image Tag → ${env.IMAGE_TAG}"
                }
            }
        }

        /* ------------------------- DOCKER LOGIN ------------------------- */
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

        /* ---------------------- DOCKER BUILD & PUSH --------------------- */
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

        /* ------------------------ IMAGE TRIVY SCAN ----------------------- */
        stage('Trivy Image Scan') {
            when { expression { return env.DEPLOY_ENV != null && !params.ROLLBACK } }
            steps {
                sh """
                    trivy image ${env.IMAGE_NAME}:${env.IMAGE_TAG} \
                    --severity HIGH,CRITICAL \
                    > trivyimage.txt || true
                """
            }
        }
    }
}
