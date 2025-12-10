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
        booleanParam(name: 'ROLLBACK', defaultValue: false, description: 'Rollback to TARGET_VERSION instead of deploy')
        string(name: 'TARGET_VERSION', defaultValue: '', description: 'Target Docker tag for rollback (if enabled)')
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
                        ]],
                        extensions: [
                            [$class: 'CloneOption', shallow: false, noTags: false],   // <-- IMPORTANT
                            [$class: 'CheckoutOption']
                        ]
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
                        env.IMAGE_NAME = "anrs125/sample-private"
                        env.TAG_TYPE = "commit"

                    } else if (env.ACTUAL_BRANCH == "master") {
                        env.DEPLOY_ENV = "production"
                        env.IMAGE_NAME = "anrs125/sample-private"
                        env.TAG_TYPE = "release"

                    } else {
                        error("Unsupported branch: ${env.ACTUAL_BRANCH}")
                    }

                    echo """
                    Environment Info:
                    -----------------------
                    Branch: ${env.ACTUAL_BRANCH}
                    Deploy Env: ${env.DEPLOY_ENV}
                    Repo: ${env.IMAGE_NAME}
                    Tag Mode: ${env.TAG_TYPE}
                    """
                }
            }
        }

        stage('Trivy Filesystem Scan') {
            steps {
                script {
                    echo "Running Trivy filesystem scan..."
                    sh "trivy fs . --severity HIGH,CRITICAL > trivyfs.txt || true"
                }
            }
        }

        stage('Generate Docker Tag') {
            steps {
                script {
                    def commitId = sh(script: "git rev-parse --short HEAD", returnStdout: true).trim()
                    def imageTag = ""

                    if (params.ROLLBACK) {
                        if (!params.TARGET_VERSION?.trim()) {
                            error("Rollback requires TARGET_VERSION.")
                        }
                        imageTag = params.TARGET_VERSION.trim()

                    } else if (env.TAG_TYPE == "commit") {
                        // STAGING → staging-commitID
                        imageTag = "staging-${commitId}"

                    } else if (env.TAG_TYPE == "release") {
                        // PRODUCTION → MUST use Git Tag
                        def gitTag = sh(
                            script: "git name-rev --name-only --tags HEAD | sed 's/\\^.*//'",
                            returnStdout: true
                        ).trim()

                        if (gitTag && gitTag != "undefined") {
                            echo "✔ Git Tag detected: ${gitTag}"
                            imageTag = gitTag
                        } else {
                            error("❌ No Git Tag found on master commit. Create tag using: git tag v1.0.0 && git push origin v1.0.0")
                        }
                    }

                    env.IMAGE_TAG = imageTag
                    echo "✔ Final Docker Image Tag: ${env.IMAGE_TAG}"
                }
            }
        }

        stage('🔐 Docker Login') {
            steps {
                script {
                    withCredentials([usernamePassword(credentialsId: env.DOCKER_CREDENTIALS_ID,
                        usernameVariable: 'DOCKER_USER', passwordVariable: 'DOCKER_PASSWORD')]) {

                        sh """
                            echo ${DOCKER_PASSWORD} | docker login -u ${DOCKER_USER} --password-stdin
                        """
                    }
                }
            }
        }

        stage('Docker Build & Push') {
            when { expression { return !params.ROLLBACK } }
            steps {
                script {
                    def imageFull = "${env.IMAGE_NAME}:${env.IMAGE_TAG}"
                    echo "Building Docker image: ${imageFull}"

                    sh """
                        docker build --pull --no-cache -t ${imageFull} .
                        docker push ${imageFull}
                    """
                }
            }
        }

        stage('🛡️ Trivy Image Scan') {
            when { expression { return !params.ROLLBACK } }
            steps {
                script {
                    sh """
                        trivy image ${env.IMAGE_NAME}:${env.IMAGE_TAG} --severity HIGH,CRITICAL > trivyimage.txt || true
                    """
                }
            }
        }
    }
}
