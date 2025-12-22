pipeline {
    agent any

    options {
        disableConcurrentBuilds()
        timestamps()
        timeout(time: 60, unit: 'MINUTES')
    }

    environment {
        GIT_REPO              = "https://github.com/Anandreddy125/project-management.git"
        GIT_CREDENTIALS_ID    = "github-anand"
        DOCKER_CREDENTIALS_ID = "docker-test"

        DEPLOY_ENV  = "staging"
        IMAGE_NAME  = "anrs125/testing-repo"
    }

    parameters {
        choice(
            name: 'BRANCH_PARAM',
            choices: ['staging', 'master'],
            description: 'Select branch to build manually'
        )
    }

    triggers {
        githubPush()
    }

    stages {

        /* ---------------- CLEAN ---------------- */
        stage('Clean Workspace') {
            steps {
                cleanWs()
            }
        }

        /* ---------------- CHECKOUT ---------------- */
        stage('Checkout Code') {
            steps {
                script {
                    def branchName = env.BRANCH_NAME ?: params.BRANCH_PARAM
                    env.ACTUAL_BRANCH = branchName

                    checkout([
                        $class: 'GitSCM',
                        branches: [[name: branchName]],
                        userRemoteConfigs: [[
                            url: env.GIT_REPO,
                            credentialsId: env.GIT_CREDENTIALS_ID
                        ]]
                    ])

                    echo "Checked out branch: ${env.ACTUAL_BRANCH}"
                }
            }
        }

        /* ---------------- IMAGE TAG ---------------- */
        stage('Generate Image Tag') {
            steps {
                script {
                    def commitId = sh(
                        script: "git rev-parse --short HEAD",
                        returnStdout: true
                    ).trim()

                    env.IMAGE_TAG = "staging-${commitId}"
                    echo "Docker Image Tag: ${env.IMAGE_TAG}"
                }
            }
        }

        /* ---------------- DOCKER BUILD & PUSH ---------------- */
        stage('Docker Build & Push') {
            steps {
                withCredentials([
                    usernamePassword(
                        credentialsId: env.DOCKER_CREDENTIALS_ID,
                        usernameVariable: 'DOCKER_USER',
                        passwordVariable: 'DOCKER_PASSWORD'
                    )
                ]) {
                    sh """
                        echo "$DOCKER_PASSWORD" | docker login -u "$DOCKER_USER" --password-stdin
                        docker build -t ${IMAGE_NAME}:${IMAGE_TAG} .
                        docker push ${IMAGE_NAME}:${IMAGE_TAG}
                        docker logout
                    """
                }
            }
        }
    }

    post {
        success {
            echo "✅ Build successful"
            echo "Image pushed: ${IMAGE_NAME}:${IMAGE_TAG}"
        }

        failure {
            echo "❌ Build failed"
        }

        always {
            echo "Pipeline completed."
            cleanWs()
        }
    }
}
