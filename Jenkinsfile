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
        githubPush()   // CI + TAG triggered
    }

    stages {

        /* --------------------- CLEAN ---------------------- */
        stage('Clean Workspace') {
            steps { cleanWs() }
        }

        /* --------------------- CHECKOUT ---------------------- */
        stage('Checkout Code') {
            steps {
                script {
                    echo "🔹 Checking out code (supports branches + tags)"

                    checkout([$class: 'GitSCM',
                        branches: [[name: "**"]],  // supports main/master/tags
                        userRemoteConfigs: [[
                            url: env.GIT_REPO,
                            credentialsId: env.GIT_CREDENTIALS_ID
                        ]],
                        extensions: [
                            [$class: "CloneOption", shallow: false, noTags: false]
                        ]
                    ])

                    env.GIT_REF = sh(script: "git rev-parse --symbolic-full-name HEAD", returnStdout: true).trim()
                    echo "✔ REF: ${env.GIT_REF}"

                    if (env.GIT_REF.startsWith("refs/heads/")) {
                        env.ACTUAL_BRANCH = env.GIT_REF.replace("refs/heads/", "")
                    } else if (env.GIT_REF.startsWith("refs/tags/")) {
                        env.GIT_TAG = env.GIT_REF.replace("refs/tags/", "")
                        env.ACTUAL_BRANCH = "master"  // Production release only from master tags
                    }

                    echo "✔ Branch Detected: ${env.ACTUAL_BRANCH}"
                    if (env.GIT_TAG) echo "✔ Tag Detected: ${env.GIT_TAG}"
                }
            }
        }

        /* --------------------- CHOOSE ENV ---------------------- */
        stage('Determine Environment') {
            steps {
                script {

                    if (env.ACTUAL_BRANCH == "main") {
                        /* STAGING ENVIRONMENT */
                        env.DEPLOY_ENV = "staging"
                        env.IMAGE_NAME = "anrs125/sample-private"
                        env.TAG_TYPE   = "commit"

                    } else if (env.GIT_TAG && env.ACTUAL_BRANCH == "master") {
                        /* PRODUCTION ENVIRONMENT (only tag builds release) */
                        env.DEPLOY_ENV = "production"
                        env.IMAGE_NAME = "anrs125/sample-private"
                        env.TAG_TYPE   = "release"

                    } else {
                        echo "⛔ Not staging or a master-tag build. Skipping."
                        currentBuild.result = "NOT_BUILT"
                        return
                    }

                    echo """
                    =============================
                       DEPLOYMENT CONFIGURATION
                    =============================
                    Branch:        ${env.ACTUAL_BRANCH}
                    Environment:   ${env.DEPLOY_ENV}
                    Docker Repo:   ${env.IMAGE_NAME}
                    Mode:          ${env.TAG_TYPE}
                    Tag:           ${env.GIT_TAG ?: 'N/A'}
                    =============================
                    """
                }
            }
        }

        /* --------------------- TRIVY FS ---------------------- */
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
                        /* STAGING TAG */
                        env.IMAGE_TAG = "staging-${commitId}"

                    } else if (env.TAG_TYPE == "release") {
                        /* PRODUCTION TAG = EXACT GIT TAG */
                        env.IMAGE_TAG = env.GIT_TAG
                    }

                    echo "✔ Final Docker Tag: ${env.IMAGE_TAG}"
                }
            }
        }

        /* --------------------- DOCKER LOGIN ---------------------- */
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

        /* --------------------- DOCKER BUILD & PUSH ---------------------- */
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

        /* --------------------- IMAGE SCAN ---------------------- */
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
