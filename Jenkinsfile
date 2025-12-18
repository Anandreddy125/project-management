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

        STAGING_IMAGE    = "anrs125/reports-staging"
        PRODUCTION_IMAGE = "anrs125/reports-staging"
    }

    stages {

        /* ===================== CONTEXT ===================== */
        stage('Detect Build Context') {
            steps {
                script {
                    env.IS_TAG    = (env.GIT_BRANCH?.startsWith('refs/tags/')) ? "true" : "false"
                    env.TAG_NAME  = env.IS_TAG == "true" ? env.GIT_BRANCH.replace('refs/tags/', '') : ""
                    env.BRANCH    = env.BRANCH_NAME

                    echo "Branch Name : ${env.BRANCH}"
                    echo "Git Branch  : ${env.GIT_BRANCH}"
                    echo "Is Tag      : ${env.IS_TAG}"
                    echo "Tag Name    : ${env.TAG_NAME}"

                    /* Abort master branch push builds */
                    if (env.BRANCH == "master" && env.IS_TAG == "false") {
                        error("❌ Master branch push detected. Production builds require TAG only.")
                    }
                }
            }
        }

        /* ===================== CLEAN ===================== */
        stage('Clean Workspace') {
            steps { cleanWs() }
        }

        /* ===================== CHECKOUT ===================== */
        stage('Checkout Code') {
            steps {
                checkout scm
            }
        }

        /* ===================== BUILD ===================== */
        stage('Build Docker Image') {
            steps {
                script {
                    if (env.IS_TAG == "true") {
                        echo "🚀 Production build for tag ${env.TAG_NAME}"
                        docker.withRegistry('', env.DOCKER_CREDENTIALS_ID) {
                            sh """
                              docker build -t ${PRODUCTION_IMAGE}:${TAG_NAME} .
                              docker push ${PRODUCTION_IMAGE}:${TAG_NAME}
                            """
                        }
                    } else if (env.BRANCH == "staging") {
                        echo "🧪 Staging build"
                        docker.withRegistry('', env.DOCKER_CREDENTIALS_ID) {
                            sh """
                              docker build -t ${STAGING_IMAGE}:${BUILD_NUMBER} .
                              docker push ${STAGING_IMAGE}:${BUILD_NUMBER}
                            """
                        }
                    } else {
                        error("❌ Unsupported branch: ${env.BRANCH}")
                    }
                }
            }
        }

        /* ===================== DEPLOY ===================== */
        stage('Deploy') {
            steps {
                script {
                    if (env.IS_TAG == "true") {
                        echo "🚀 Deploying production version ${TAG_NAME}"
                        // kubectl apply -f k8s/production.yaml
                    } else if (env.BRANCH == "staging") {
                        echo "🧪 Deploying staging build"
                        // kubectl apply -f k8s/staging.yaml
                    }
                }
            }
        }
    }

    post {
        success {
            echo "✅ Pipeline completed successfully"
        }
        failure {
            echo "❌ Pipeline failed"
        }
    }
}
