pipeline {
    agent any

    options {
        disableConcurrentBuilds()
        timestamps()
        timeout(time: 60, unit: 'MINUTES')
    }

    environment {
        GIT_CREDENTIALS_ID    = "github-anand"
        DOCKER_CREDENTIALS_ID = "docker-test"
        IMAGE_NAME            = "anrs125/testing-repo"
    }

    stages {

        /* ================= CHECKOUT ================= */
        stage('Checkout') {
            steps {
                checkout scm
                script {
                    echo "BRANCH_NAME = ${env.BRANCH_NAME}"
                    echo "TAG_NAME    = ${env.TAG_NAME ?: 'N/A'}"
                }
            }
        }

        /* ================= STAGING ================= */
        stage('Staging Build') {
            when {
                allOf {
                    branch 'staging'
                    not { buildingTag() }
                }
            }
            steps {
                script {
                    def commitId = sh(
                        script: "git rev-parse --short HEAD",
                        returnStdout: true
                    ).trim()

                    env.IMAGE_TAG = "staging-${commitId}"
                    echo "Staging image tag: ${env.IMAGE_TAG}"
                }

                withCredentials([usernamePassword(
                    credentialsId: env.DOCKER_CREDENTIALS_ID,
                    usernameVariable: 'DOCKER_USER',
                    passwordVariable: 'DOCKER_PASSWORD'
                )]) {
                    sh """
                        echo "\$DOCKER_PASSWORD" | docker login -u "\$DOCKER_USER" --password-stdin
                        docker build -t ${IMAGE_NAME}:${IMAGE_TAG} .
                        docker push ${IMAGE_NAME}:${IMAGE_TAG}
                    """
                }

                echo "✅ STAGING deployment completed"
            }
        }

        /* ================= PRODUCTION ================= */
        stage('Production Build') {
            when {
                buildingTag()
            }
            steps {
                script {
                    env.IMAGE_TAG = env.TAG_NAME
                    echo "Production tag detected: ${env.IMAGE_TAG}"
                }

                /* ---- Enforce tag must be from master ---- */
                sh '''
                    git branch -r --contains ${IMAGE_TAG} | grep origin/master
                '''

                withCredentials([usernamePassword(
                    credentialsId: env.DOCKER_CREDENTIALS_ID,
                    usernameVariable: 'DOCKER_USER',
                    passwordVariable: 'DOCKER_PASSWORD'
                )]) {
                    sh """
                        echo "\$DOCKER_PASSWORD" | docker login -u "\$DOCKER_USER" --password-stdin
                        docker build -t ${IMAGE_NAME}:${IMAGE_TAG} .
                        docker push ${IMAGE_NAME}:${IMAGE_TAG}
                    """
                }

                echo "✅ PRODUCTION deployment completed"
            }
        }
    }

    post {
        always {
            cleanWs()
        }
    }
}
//created new pipeline job and testing with farhan on testing env
// removed on jenkinsfile line no 5 skip default checkout.
// changed webhook configuration
// updated jenkinfile