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
        choice(name: 'BRANCH_PARAM', choices: ['main', 'master'], description: 'Select branch to build manually')
    }

    triggers {
        githubPush()
    }

    stages {

        stage('Clean Workspace') {
            steps { cleanWs() }
        }

        stage('Checkout Code') {
            steps {
                script {
                    def branchName = env.BRANCH_NAME ?: params.BRANCH_PARAM
                    echo "🔹 Checking out branch: ${branchName}"

                    checkout([$class: 'GitSCM',
                        branches: [[name: "*/${branchName}"]],
                        userRemoteConfigs: [[
                            url: env.GIT_REPO,
                            credentialsId: env.GIT_CREDENTIALS_ID
                        ]]
                    ])

                    env.ACTUAL_BRANCH = branchName
                }
            }
        }

        stage('Determine Environment') {
            steps {
                script {
                    if (env.ACTUAL_BRANCH == "staging") {
                        env.DEPLOY_ENV = "staging"
                        env.IMAGE_NAME = "anrs125/reports-tesing"
                        env.TAG_TYPE = "commit"

                    } else if (env.ACTUAL_BRANCH == "master") {
                        env.DEPLOY_ENV = "production"
                        env.IMAGE_NAME = "anrs125/reports-tesing"
                        env.TAG_TYPE = "release"

                    } else {
                        error("Unsupported branch: ${env.ACTUAL_BRANCH}")
                    }

                    echo """
                    Environment Info
                    ----------------------
                    Branch: ${env.ACTUAL_BRANCH}
                    Deploy: ${env.DEPLOY_ENV}
                    Repo:   ${env.IMAGE_NAME}
                    Mode:   ${env.TAG_TYPE}
                    """
                }
            }
        }

        stage('Generate Docker Tag') {
            steps {
                script {
                    def commitId = sh(script: "git rev-parse HEAD | cut -c1-7", returnStdout: true).trim()
                    def imageTag = ""

                    if (env.TAG_TYPE == "commit") {
                        imageTag = "staging-${commitId}"

                    } else if (env.TAG_TYPE == "release") {
                        def tagName = sh(
                            script: "git describe --tags --exact-match HEAD 2>/dev/null || true",
                            returnStdout: true
                        ).trim()

                        if (!tagName) {
                            error("""
❌ No Git tag found on this commit!
Production builds *require* a Git Tag.
EXAMPLE:
    git tag -a v1.0.3 -m "Release 1.0.3"
    git push --tags
""")
                        }

                        imageTag = tagName
                    }

                    env.IMAGE_TAG = imageTag
                    echo "🚀 FINAL Docker Tag: ${env.IMAGE_TAG}"
                }
            }
        }

        stage('Trivy Filesystem Scan') {
            steps {
                script {
                    echo "Running Trivy filesystem scan..."
                    sh "trivy fs . --severity HIGH,CRITICAL > trivyfs.txt || true"
                    echo "Filesystem scan completed — saved in trivyfs.txt"
                }
            }
        }

        stage('🔐 Docker Login') {
            steps {
                script {
                    withCredentials([usernamePassword(credentialsId: env.DOCKER_CREDENTIALS_ID,
                        usernameVariable: 'DOCKER_USER', passwordVariable: 'DOCKER_PASSWORD')]) {
                        sh "echo ${DOCKER_PASSWORD} | docker login -u ${DOCKER_USER} --password-stdin"
                    }
                }
            }
        }

        stage('Docker Build & Push') {
            steps {
                script {
                    def imageFull = "${env.IMAGE_NAME}:${env.IMAGE_TAG}"
                    echo "Building Docker image: ${imageFull}"

                    sh """
                        docker build --pull --no-cache -t ${imageFull} .
                        docker push ${imageFull}
                    """
                    sh "docker logout"
                }
            }
        }
    }

    post {
        always {
            echo 'Pipeline completed.'
            cleanWs()
        }
    }
}
