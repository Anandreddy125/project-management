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
                    echo "🔹 Checking out TAG source code..."

                    checkout([$class: 'GitSCM',
                        branches: [[name: "refs/tags/*"]],  // Only tags trigger checkout
                        userRemoteConfigs: [[
                            url: env.GIT_REPO,
                            credentialsId: env.GIT_CREDENTIALS_ID
                        ]],
                        extensions: [
                            [$class: 'CloneOption', shallow: false, noTags: false],
                            [$class: 'CheckoutOption']
                        ]
                    ])

                    /**********************************************
                     Detect ORIGINAL branch where the tag was created
                     **********************************************/
                    env.SOURCE_BRANCH = sh(
                        script: "git for-each-ref --format='%(refname:short)' --points-at HEAD refs/remotes/origin/* | sed 's/origin\\///' | head -1",
                        returnStdout: true
                    ).trim()

                    echo "✔ Tag created from branch: ${env.SOURCE_BRANCH}"
                }
            }
        }


        stage('Determine Environment') {
            steps {
                script {

                    if (env.SOURCE_BRANCH == "main") {
                        // STAGING ENVIRONMENT
                        env.DEPLOY_ENV = "staging"
                        env.TAG_TYPE   = "commit"
                        env.IMAGE_NAME = "anrs125/sample-private"

                    } else if (env.SOURCE_BRANCH == "master") {
                        // PRODUCTION ENVIRONMENT
                        env.DEPLOY_ENV = "production"
                        env.TAG_TYPE   = "release"
                        env.IMAGE_NAME = "anrs125/sample-private"

                    } else {
                        error("❌ Unsupported or unknown branch for tag: ${env.SOURCE_BRANCH}")
                    }

                    echo """
                    =============================
                       DEPLOYMENT CONFIGURATION
                    =============================
                    Tag Source Branch: ${env.SOURCE_BRANCH}
                    Deployment Env:    ${env.DEPLOY_ENV}
                    Docker Repo:       ${env.IMAGE_NAME}
                    Tag Mode:          ${env.TAG_TYPE}
                    """
                }
            }
        }

        stage('Trivy Filesystem Scan') {
            steps {
                sh "trivy fs . --severity HIGH,CRITICAL > trivyfs.txt || true"
            }
        }

        stage('Generate Docker Tag') {
            steps {
                script {

                    def commitId = sh(
                        script: "git rev-parse --short HEAD",
                        returnStdout: true
                    ).trim()

                    def imageTag = ""

                    if (params.ROLLBACK) {
                        if (!params.TARGET_VERSION.trim()) {
                            error("Rollback requires TARGET_VERSION.")
                        }
                        imageTag = params.TARGET_VERSION.trim()

                    } else if (env.TAG_TYPE == "commit") {

                        /** STAGING TAG LOGIC */
                        imageTag = "staging-${commitId}"

                    } else if (env.TAG_TYPE == "release") {

                        /** PRODUCTION MUST USE GIT TAG EXACTLY */
                        def gitTag = env.GIT_TAG ?: sh(
                            script: "git describe --tags --exact-match HEAD || true",
                            returnStdout: true
                        ).trim()

                        if (!gitTag) {
                            error("❌ No Git Tag found! Push using: git tag v1.0.0 && git push origin v1.0.0")
                        }

                        echo "✔ Production Git Tag detected: ${gitTag}"
                        imageTag = gitTag
                    }

                    env.IMAGE_TAG = imageTag

                    echo """
                    =============================
                    ✔ Final Docker Image Tag:
                    ${env.IMAGE_TAG}
                    =============================
                    """
                }
            }
        }

        stage('🔐 Docker Login') {
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
                sh """
                    trivy image ${env.IMAGE_NAME}:${env.IMAGE_TAG} --severity HIGH,CRITICAL > trivyimage.txt || true
                """
            }
        }
    }
}
