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
        githubPush()   // supports both branch pushes & tag pushes
    }

    parameters {
        booleanParam(name: 'ROLLBACK', defaultValue: false, description: 'Rollback to TARGET_VERSION?')
        string(name: 'TARGET_VERSION', defaultValue: '', description: 'Rollback version for PROD only')
    }

    stages {

        /* ---------------- CLEAN ---------------- */
        stage('Clean Workspace') {
            steps { cleanWs() }
        }

        /* ---------------- CHECKOUT CODE ---------------- */
        stage('Checkout Code') {
            steps {
                script {
                    echo "🔹 Checking out code (branches + tags supported)"

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

                    /* Detect branch or tag */
                    def ref = sh(script: "git symbolic-ref -q HEAD || true", returnStdout: true).trim()

                    if (ref.startsWith("refs/heads/")) {
                        env.ACTUAL_BRANCH = ref.replace("refs/heads/", "")
                        echo "✔ Branch detected: ${env.ACTUAL_BRANCH}"

                    } else {
                        def tag = sh(script: "git describe --tags --exact-match HEAD 2>/dev/null || true",
                                     returnStdout: true).trim()

                        if (tag) {
                            env.GIT_TAG = tag
                            env.ACTUAL_BRANCH = "master"
                            echo "✔ Tag build detected: ${env.GIT_TAG}"
                        }
                    }
                }
            }
        }

        /* ---------------- DETERMINE ENVIRONMENT ---------------- */
        stage('Determine Environment') {
            steps {
                script {

                    if (env.ACTUAL_BRANCH == "main") {
                        /* STAGING (branch push) */
                        env.DEPLOY_ENV = "staging"
                        env.IMAGE_NAME = "anrs125/sample-private"
                        env.TAG_TYPE = "commit"

                    } else if (env.ACTUAL_BRANCH == "master" && env.GIT_TAG) {
                        /* PRODUCTION (must be tag push) */
                        env.DEPLOY_ENV = "production"
                        env.IMAGE_NAME = "prophazedocker/i-report"
                        env.TAG_TYPE = "release"

                    } else {
                        echo "⛔ Skipping: Not staging or production tag build"
                        currentBuild.result = "NOT_BUILT"
                        return
                    }

                    echo """
                    ================================
                        BUILD CONFIGURATION
                    ================================
                    Branch:        ${env.ACTUAL_BRANCH}
                    Environment:   ${env.DEPLOY_ENV}
                    Docker Repo:   ${env.IMAGE_NAME}
                    Mode:          ${env.TAG_TYPE}
                    Tag Found:     ${env.GIT_TAG ?: "N/A"}
                    =================================
                    """
                }
            }
        }

        /* ---------------- TRIVY FS SCAN ---------------- */
        stage('Trivy FS Scan') {
            steps {
                sh "trivy fs . --severity HIGH,CRITICAL > trivyfs.txt || true"
            }
        }

        /* ---------------- GENERATE DOCKER TAG ---------------- */
        stage('Generate Docker Tag') {
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

                    echo "✔ Final Docker Tag: ${env.IMAGE_TAG}"
                }
            }
        }

        /* ---------------- DOCKER LOGIN + BUILD + PUSH ---------------- */
        stage('Docker Build & Push') {
            when { expression { return !params.ROLLBACK } }
            steps {
                script {
                    withCredentials([usernamePassword(
                        credentialsId: env.DOCKER_CREDENTIALS_ID,
                        usernameVariable: 'DOCKER_USER',
                        passwordVariable: 'DOCKER_PASSWORD'
                    )]) {

                        sh "echo ${DOCKER_PASSWORD} | docker login -u ${DOCKER_USER} --password-stdin"

                        def img = "${env.IMAGE_NAME}:${env.IMAGE_TAG}"

                        sh """
                            docker build --pull --no-cache -t ${img} .
                            docker push ${img}
                        """
                    }
                }
            }
        }

        /* ---------------- TRIVY IMAGE SCAN ---------------- */
        stage('Trivy Image Scan') {
            when { expression { return !params.ROLLBACK } }
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
