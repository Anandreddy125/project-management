pipeline {
    agent any

    options {
        disableConcurrentBuilds()
        timestamps()
        timeout(time: 60, unit: 'MINUTES')
    }

    environment {
        // Repository & credentials
        GIT_REPO              = "https://github.com/Anandreddy125/project-management.git"
        GIT_CREDENTIALS_ID    = "terra-github"
        DOCKER_CREDENTIALS_ID = "anand-dockerhub"

        // Dynamic variables (initialized empty)
        IMAGE_NAME            = ""
        DEPLOY_ENV            = ""
        TAG_TYPE              = ""
        IMAGE_TAG             = ""
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

        stage('🔍 Check Trigger Type') {
            steps {
                script {
                    // Ignore non-master tag pushes
                    if (env.GIT_BRANCH?.startsWith("refs/tags/") || env.BRANCH_NAME?.startsWith("refs/tags/")) {
                        def tagRef = env.GIT_BRANCH ?: env.BRANCH_NAME
                        echo "🚫 Tag push detected: ${tagRef}"

                        if (!tagRef.contains("master")) {
                            echo "⏭️ Skipping build for non-master tag push."
                            currentBuild.result = 'SUCCESS'
                            return
                        }
                    }

                    echo "✅ Normal branch push detected — proceeding with pipeline."
                }
            }
        }

        stage('🧹 Clean Workspace') {
            steps {
                cleanWs()
            }
        }

        stage('📥 Checkout Code') {
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
                            [$class: 'CloneOption', depth: 0, noTags: false, shallow: false],
                            [$class: 'CheckoutOption', timeout: 30]
                        ]
                    ])

                    env.ACTUAL_BRANCH = branchName
                }
            }
        }

        stage('🌿 Determine Environment') {
            steps {
                script {
                    if (env.ACTUAL_BRANCH == "main" || env.ACTUAL_BRANCH == "staging") {
                        env.DEPLOY_ENV = "staging"
                        env.IMAGE_NAME = "anrs125/farhan-testing"
                        env.TAG_TYPE   = "commit"
                    } else if (env.ACTUAL_BRANCH == "master") {
                        env.DEPLOY_ENV = "production"
                        env.IMAGE_NAME = "anrs125/sample-private"
                        env.TAG_TYPE   = "release"
                    } else {
                        error("❌ Unsupported branch: ${env.ACTUAL_BRANCH}")
                    }

                    echo """
                    🌍 Environment Details
                    ---------------------
                    Branch:        ${env.ACTUAL_BRANCH}
                    Environment:   ${env.DEPLOY_ENV}
                    Docker Image:  ${env.IMAGE_NAME}
                    Tag Type:      ${env.TAG_TYPE}
                    """
                }
            }
        }

        stage('🏷️ Set Image Tag') {
            steps {
                script {
                    def commitId  = sh(script: "git rev-parse --short HEAD", returnStdout: true).trim()
                    def timestamp = new Date().format("yyyyMMdd-HHmmss", TimeZone.getTimeZone("UTC"))
                    def imageTag  = ""

                    if (params.ROLLBACK) {
                        if (!params.TARGET_VERSION?.trim()) {
                            error("Rollback requested but TARGET_VERSION is empty.")
                        }
                        imageTag = params.TARGET_VERSION.trim()
                        echo "⤴️ Rollback mode — using tag ${imageTag}"

                    } else if (env.TAG_TYPE == "commit") {
                        // Staging: commit + timestamp
                        imageTag = "staging-${commitId}-${timestamp}"
                        echo "🏷️ Using commit-based staging tag: ${imageTag}"

                    } else {
                        // Production: use Git tag if available
                        def tagName = sh(script: "git describe --tags --exact-match HEAD 2>/dev/null || true", returnStdout: true).trim()
                        if (!tagName) {
                            echo "⚠️ No Git tag found, using fallback build tag."
                            imageTag = "build-${commitId}-${timestamp}"
                        } else {
                            imageTag = tagName
                        }
                        echo "🏷️ Final production tag: ${imageTag}"
                    }

                    // ✅ Persist globally
                    env.IMAGE_TAG = imageTag
                    echo "✅ Exported IMAGE_TAG globally: ${env.IMAGE_TAG}"
                }
            }
        }

        stage('🔐 Docker Login') {
            steps {
                script {
                    withCredentials([usernamePassword(credentialsId: env.DOCKER_CREDENTIALS_ID, usernameVariable: 'DOCKER_USER', passwordVariable: 'DOCKER_PASSWORD')]) {
                        sh """
                            echo ${DOCKER_PASSWORD} | docker login -u ${DOCKER_USER} --password-stdin
                        """
                    }
                }
            }
        }

        stage('🐳 Docker Build & Push') {
            when { expression { return !params.ROLLBACK } }
            steps {
                script {
                    echo "🧾 Building image: ${env.IMAGE_NAME}:${env.IMAGE_TAG}"
                    sh """
                        docker build --pull --no-cache -t ${env.IMAGE_NAME}:${env.IMAGE_TAG} .
                        docker push ${env.IMAGE_NAME}:${env.IMAGE_TAG}
                    """

                    // Push "latest" only for production
                    if (env.DEPLOY_ENV == "production") {
                        sh """
                            docker tag ${env.IMAGE_NAME}:${env.IMAGE_TAG} ${env.IMAGE_NAME}:latest
                            docker push ${env.IMAGE_NAME}:latest
                        """
                        echo "✅ Production image also tagged as 'latest'."
                    }

                    sh "docker logout"
                    echo "✅ Successfully pushed image: ${env.IMAGE_NAME}:${env.IMAGE_TAG}"
                }
            }
        }

        stage('⤴️ Rollback (Manual Trigger Only)') {
            when { expression { return params.ROLLBACK && params.TARGET_VERSION?.trim() } }
            steps {
                script {
                    echo "⚙️ Rollback requested to version: ${params.TARGET_VERSION}"
                    echo "Skipping build, using existing image: ${env.IMAGE_NAME}:${params.TARGET_VERSION}"
                }
            }
        }
    }

    post {
        success {
            echo """
            ✅ Build & Push Successful!
            ---------------------------
            🌍 Environment: ${env.DEPLOY_ENV}
            📦 Image: ${env.IMAGE_NAME}:${env.IMAGE_TAG}
            🕓 Time: ${new Date().format("yyyy-MM-dd HH:mm:ss", TimeZone.getTimeZone("UTC"))}
            """
        }
        failure {
            echo "❌ Pipeline failed. Please review the build logs."
        }
        always {
            cleanWs()
        }
    }
}
