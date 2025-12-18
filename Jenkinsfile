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

        DEPLOY_ENV            = "production"
        IMAGE_NAME            = "anrs125/reports-tesing"
        KUBERNETES_CREDENTIALS_ID = "k3s-report-staging"
        DEPLOYMENT_FILE       = "prod-reports.yaml"
        DEPLOYMENT_NAME       = "prod-reports-api"
    }

    parameters {
        booleanParam(
            name: 'ROLLBACK',
            defaultValue: false,
            description: 'Rollback to TARGET_VERSION instead of deploy'
        )
        string(
            name: 'TARGET_VERSION',
            defaultValue: '',
            description: 'Docker tag for rollback (required if ROLLBACK=true)'
        )
    }

    triggers {
        githubPush()
    }

    stages {

        stage('Clean Workspace') {
            steps { cleanWs() }
        }

        stage('Checkout Code (master only)') {
            steps {
                checkout([$class: 'GitSCM',
                    branches: [[name: "*/master"]],
                    userRemoteConfigs: [[
                        url: env.GIT_REPO,
                        credentialsId: env.GIT_CREDENTIALS_ID
                    ]]
                ])
            }
        }

        stage('Generate Docker Tag') {
            steps {
                script {
                    def imageTag = ""

                    if (params.ROLLBACK) {
                        if (!params.TARGET_VERSION?.trim()) {
                            error("Rollback requested but TARGET_VERSION is empty")
                        }
                        imageTag = params.TARGET_VERSION.trim()
                    } else {
                        def tagName = sh(
                            script: "git describe --tags --exact-match HEAD 2>/dev/null || true",
                            returnStdout: true
                        ).trim()

                        if (!tagName) {
                            error("No Git tag found on master. Production deploy requires a tag.")
                        }
                        imageTag = tagName
                    }

                    env.IMAGE_TAG = imageTag
                    echo "🚀 Docker tag selected: ${env.IMAGE_TAG}"
                }
            }
        }

        stage('Docker Login') {
            steps {
                withCredentials([
                    usernamePassword(
                        credentialsId: env.DOCKER_CREDENTIALS_ID,
                        usernameVariable: 'DOCKER_USER',
                        passwordVariable: 'DOCKER_PASSWORD'
                    )
                ]) {
                    sh "echo ${DOCKER_PASSWORD} | docker login -u ${DOCKER_USER} --password-stdin"
                }
            }
        }

        stage('Docker Build & Push') {
            when { expression { !params.ROLLBACK } }
            steps {
                script {
                    def imageFull = "${env.IMAGE_NAME}:${env.IMAGE_TAG}"
                    echo "Building & pushing image: ${imageFull}"

                    sh """
                        docker build --pull --no-cache -t ${imageFull} .
                        docker push ${imageFull}
                    """
                }
            }
        }

        stage('Deploy to Production') {
            steps {
                echo "Deploying ${DEPLOYMENT_NAME} using ${DEPLOYMENT_FILE}"
                // kubectl / helm deploy step goes here
            }
        }
    }
}
