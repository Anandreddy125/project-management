pipeline {
    agent any

    options {
        disableConcurrentBuilds()
        timestamps()
        timeout(time: 60, unit: 'MINUTES')
    }

    environment {
        SCANNER_HOME          = tool('sonar-scanner')
        DOCKER_CREDENTIALS_ID = "anand-dockerhub"

        IMAGE_NAME            = "anrs125/reports-tesing"
     //   DEPLOYMENT_FILE       = "staging-report.yaml"
     //   DEPLOYMENT_NAME       = "staging-reports-api"
     //   KUBE_CRED             = "reports-staging"

     //   SLACK_CHANNEL = "#jenkins-alerts"
     //   SLACK_CRED    = "slack-token"
    }

    triggers {
        githubPush()
    }

    stages {

        stage('Validate Branch') {
            steps {
                script {
                    if (env.BRANCH_NAME != "staging") {
                        error("❌ This pipeline runs only for staging branch")
                    }
                }
            }
        }

        stage('Clean Workspace') {
            steps { cleanWs() }
        }

        stage('Checkout Code') {
            steps { checkout scm }
        }

        stage('Generate Docker Tag') {
            steps {
                script {
                    def commitId = sh(
                        script: "git rev-parse --short HEAD",
                        returnStdout: true
                    ).trim()
                    env.IMAGE_TAG = "staging-${commitId}"
                }
            }
        }

        stage('Docker Build & Push') {
            steps {
                withCredentials([
                    usernamePassword(
                        credentialsId: env.DOCKER_CREDENTIALS_ID,
                        usernameVariable: 'DOCKER_USER',
                        passwordVariable: 'DOCKER_PASS'
                    )
                ]) {
                    sh """
                        echo \$DOCKER_PASS | docker login -u \$DOCKER_USER --password-stdin
                        docker build -t ${IMAGE_NAME}:${IMAGE_TAG} .
                        docker push ${IMAGE_NAME}:${IMAGE_TAG}
                        docker logout
                    """
                }
            }
        }
    }
}