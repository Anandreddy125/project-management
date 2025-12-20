pipeline {
    agent any

    options {
        disableConcurrentBuilds()
        timestamps()
        timeout(time: 60, unit: 'MINUTES')
    }

    environment {
        GIT_CREDENTIALS_ID    = "terra-github"
        DOCKER_CREDENTIALS_ID = "anand-dockerhub"
        IMAGE_REPO            = "anrs125/reports-tesing"
    }

    stages {

        stage('Detect Deployment Type') {
            steps {
                script {

                    def gitRef = sh(
                        script: "git symbolic-ref -q --short HEAD || git describe --tags --exact-match",
                        returnStdout: true
                    ).trim()

                    echo "Git ref detected: ${gitRef}"

                    if (gitRef == "staging") {
                        env.DEPLOY_ENV = "staging"
                        env.IMAGE_TAG  = "staging-${sh(script: 'git rev-parse --short HEAD', returnStdout: true).trim()}"

                    } else if (gitRef.startsWith("v")) {
                        env.DEPLOY_ENV = "production"
                        env.IMAGE_TAG  = gitRef

                    } else {
                        error("❌ Unsupported ref: ${gitRef}")
                    }

                    echo "DEPLOY_ENV=${env.DEPLOY_ENV}"
                    echo "IMAGE_TAG=${env.IMAGE_TAG}"
                }
            }
        }

        stage('Build & Push Docker Image') {
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
                        docker build -t ${env.IMAGE_REPO}:${env.IMAGE_TAG} .
                        docker push ${env.IMAGE_REPO}:${env.IMAGE_TAG}
                        docker logout
                    """
                }
            }
        }

        stage('Deploy') {
            steps {
                script {
                    if (env.DEPLOY_ENV == "staging") {
                        echo "🚀 Deploying to STAGING"
                        // kubectl / helm / argocd sync staging

                    } else if (env.DEPLOY_ENV == "production") {
                        echo "🔥 Deploying to PRODUCTION"
                        // kubectl / helm / argocd sync production
                    }
                }
            }
        }
    }

    post {
        success {
            echo "✅ ${env.DEPLOY_ENV.toUpperCase()} deployment successful"
        }
        failure {
            echo "❌ Deployment failed"
        }
        always {
            cleanWs()
        }
    }
}
