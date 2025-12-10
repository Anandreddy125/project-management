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
        choice(name: 'BRANCH_PARAM', choices: ['staging', 'master'], description: 'Manually select branch if needed')
        booleanParam(name: 'ROLLBACK', defaultValue: false, description: 'Rollback to TARGET_VERSION?')
        string(name: 'TARGET_VERSION', defaultValue: '', description: 'Target Docker tag for rollback')
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
                    echo "🔹 Checking out TAG-triggered source code..."

                    checkout([$class: 'GitSCM',
                        branches: [[name: "refs/tags/*"]],  // <-- ONLY TAGS
                        userRemoteConfigs: [[
                            url: env.GIT_REPO,
                            credentialsId: env.GIT_CREDENTIALS_ID
                        ]],
                        extensions: [
                            [$class: 'CloneOption', shallow: false, noTags: false],
                            [$class: 'CheckoutOption']
                        ]
                    ])

                    // Identify actual branch if user manually builds staging
                    env.ACTUAL_BRANCH = sh(script: "git branch -r --contains HEAD | sed 's/origin\\///' | head -1", returnStdout: true).trim()
                    echo "✔ Git Branch: ${env.ACTUAL_BRANCH}"
                }
            }
        }

        stage('Determine Environment') {
            steps {
                script {
                    if (env.ACTUAL_BRANCH == "staging") {
                        env.DEPLOY_ENV = "staging"
                        env.IMAGE_NAME = "anrs125/sample-private"
                        env.TAG_TYPE   = "commit"

                    } else {
                        // DEFAULT: TAG = PRODUCTION
                        env.DEPLOY_ENV = "production"
                        env.IMAGE_NAME = "anrs125/sample-private"
                        env.TAG_TYPE   = "release"
                    }

                    echo """
                    =============================
                       DEPLOYMENT CONFIGURATION
                    =============================
                    Branch Detected: ${env.ACTUAL_BRANCH}
                    Deployment Env: ${env.DEPLOY_ENV}
                    Docker Repo:    ${env.IMAGE_NAME}
                    Tag Mode:       ${env.TAG_TYPE}
                    """
                }
            }
        }

        stage('Trivy Filesystem Scan') {
            steps {
                script {
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
                        if (!params.TARGET_VERSION.trim()) {
                            error("Rollback requires TARGET_VERSION.")
                        }
                        imageTag = params.TARGET_VERSION.trim()

                    } else if (env.TAG_TYPE == "commit") {
                        imageTag = "staging-${commitId}"

                    } else if (env.TAG_TYPE == "release") {

                        // detect GIT TAG — required for production
                        def gitTag = sh(
                            script: "git name-rev --name-only --tags HEAD | sed 's/\\^.*//'",
                            returnStdout: true
                        ).trim()

                        if (gitTag && gitTag != "undefined") {
                            echo "✔ Production Git Tag detected: ${gitTag}"
                            imageTag = gitTag
                        } else {
                            error("❌ No Git Tag on commit! Push a tag like: git tag v2.0.3 && git push origin v2.0.3")
                        }
                    }

                    env.IMAGE_TAG = imageTag
                    echo "==============================="
                    echo "✔ Final Docker Image Tag: ${env.IMAGE_TAG}"
                    echo "==============================="
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
            when { expression { return !params.ROLLBACK } }
            steps {
                script {
                    def fullImage = "${env.IMAGE_NAME}:${env.IMAGE_TAG}"
                    echo "🚀 Building Docker Image → ${fullImage}"

                    sh """
                        docker build --pull --no-cache -t ${fullImage} .
                        docker push ${fullImage}
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
