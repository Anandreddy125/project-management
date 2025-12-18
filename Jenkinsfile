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
        booleanParam(
            name: 'ROLLBACK',
            defaultValue: false,
            description: 'Rollback using TARGET_VERSION'
        )
        string(
            name: 'TARGET_VERSION',
            defaultValue: '',
            description: 'Docker image tag for rollback'
        )
    }

    triggers {
        githubPush()
    }

    stages {

        /* ================= SAFETY CHECK ================= */
        stage('Validate Trigger') {
            steps {
                script {
                    echo "BRANCH_NAME = ${env.BRANCH_NAME}"
                    echo "TAG_NAME    = ${env.TAG_NAME}"

                    // ❌ Block master normal push
                    if (env.BRANCH_NAME == "master" && !env.TAG_NAME) {
                        error("❌ Master branch push is blocked. Use Git TAG for production deployment.")
                    }
                }
            }
        }

        /* ================= CHECKOUT ================= */
        stage('Checkout Code') {
            steps {
                script {
                    if (env.TAG_NAME) {
                        echo "🔖 Checking out TAG: ${env.TAG_NAME}"
                        checkout([$class: 'GitSCM',
                            branches: [[name: "refs/tags/${env.TAG_NAME}"]],
                            userRemoteConfigs: [[
                                url: env.GIT_REPO,
                                credentialsId: env.GIT_CREDENTIALS_ID
                            ]]
                        ])

                        env.DEPLOY_ENV       = "production"
                        env.IMAGE_NAME       = "anrs125/reports-tesing"
                        env.DEPLOYMENT_FILE  = "prod-reports.yaml"
                        env.DEPLOYMENT_NAME  = "prod-reports-api"

                    } else {
                        echo "🌱 Checking out BRANCH: ${env.BRANCH_NAME}"
                        checkout([$class: 'GitSCM',
                            branches: [[name: "*/${env.BRANCH_NAME}"]],
                            userRemoteConfigs: [[
                                url: env.GIT_REPO,
                                credentialsId: env.GIT_CREDENTIALS_ID
                            ]]
                        ])

                        env.DEPLOY_ENV       = "staging"
                        env.IMAGE_NAME       = "anrs125/reports-tesing"
                        env.DEPLOYMENT_FILE  = "staging-report.yaml"
                        env.DEPLOYMENT_NAME  = "staging-reports-api"
                    }
                }
            }
        }

        /* ================= IMAGE TAG ================= */
        stage('Generate Docker Tag') {
            steps {
                script {
                    if (params.ROLLBACK) {
                        if (!params.TARGET_VERSION?.trim()) {
                            error("❌ Rollback enabled but TARGET_VERSION is empty")
                        }
                        env.IMAGE_TAG = params.TARGET_VERSION

                    } else if (env.TAG_NAME) {
                        env.IMAGE_TAG = env.TAG_NAME

                    } else {
                        def commitId = sh(
                            script: "git rev-parse --short HEAD",
                            returnStdout: true
                        ).trim()
                        env.IMAGE_TAG = "staging-${commitId}"
                    }

                    echo "🚀 Final Docker Image Tag: ${env.IMAGE_TAG}"
                }
            }
        }

        /* ================= DOCKER LOGIN ================= */
        stage('Docker Login') {
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

        /* ================= BUILD & PUSH ================= */
        stage('Docker Build & Push') {
            when {
                expression { return !params.ROLLBACK }
            }
            steps {
                script {
                    def image = "${env.IMAGE_NAME}:${env.IMAGE_TAG}"
                    echo "📦 Building image: ${image}"

                    sh """
                        docker build --no-cache -t ${image} .
                        docker push ${image}
                        docker logout
                    """
                }
            }
        }
    }
}
