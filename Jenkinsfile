pipeline {
    agent any

    options {
        disableConcurrentBuilds()
        timestamps()
        timeout(time: 60, unit: 'MINUTES')
        skipDefaultCheckout(true)
    }

    environment {
        GIT_REPO              = "https://github.com/Anandreddy125/project-management.git"
        GIT_CREDENTIALS_ID    = "terra-github"
        DOCKER_CREDENTIALS_ID = "anand-dockerhub"
    }

    parameters {
        choice(
            name: 'BRANCH_PARAM',
            choices: ['staging', 'master'],
            description: 'Manual build branch (used only if not webhook)'
        )
        booleanParam(
            name: 'ROLLBACK',
            defaultValue: false,
            description: 'Rollback using TARGET_VERSION'
        )
        string(
            name: 'TARGET_VERSION',
            defaultValue: '',
            description: 'Docker tag for rollback'
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
                    echo "🔹 Checking out repository"

                    checkout([
                        $class: 'GitSCM',
                        branches: [[name: '**']],   // allows branch + tag builds
                        userRemoteConfigs: [[
                            url: env.GIT_REPO,
                            credentialsId: env.GIT_CREDENTIALS_ID
                        ]]
                    ])

                    // Detect git ref (branch or tag)
                    env.GIT_REF = sh(
                        script: "git symbolic-ref -q --short HEAD || git describe --tags --exact-match",
                        returnStdout: true
                    ).trim()

                    echo "🔹 Git Ref Detected: ${env.GIT_REF}"
                }
            }
        }

        /* ---------------- DETERMINE ENV ---------------- */
        stage('Determine Environment') {
            steps {
                script {

                    if (env.GIT_REF.startsWith("staging")) {
                        env.ACTUAL_BRANCH = "staging"
                        env.DEPLOY_ENV    = "staging"
                        env.IMAGE_NAME    = "anrs125/reports-testing"
                        env.TAG_TYPE      = "commit"

                    } else if (env.GIT_REF == "master") {
                        error("❌ Direct master branch builds are not allowed. Use Git tags.")

                    } else {
                        // TAG build (production)
                        env.ACTUAL_BRANCH = "master"
                        env.DEPLOY_ENV    = "production"
                        env.IMAGE_NAME    = "anrs125/reports-testing"
                        env.TAG_TYPE      = "release"
                    }

                    echo """
                    ===============================
                    Environment Info
                    ===============================
                    Git Ref   : ${env.GIT_REF}
                    Branch    : ${env.ACTUAL_BRANCH}
                    Deploy To : ${env.DEPLOY_ENV}
                    Tag Type  : ${env.TAG_TYPE}
                    Image Repo: ${env.IMAGE_NAME}
                    ===============================
                    """
                }
            }
        }

        /* ---------------- DOCKER TAG ---------------- */
        stage('Generate Docker Tag') {
            steps {
                script {
                    def commitId = sh(
                        script: "git rev-parse --short HEAD",
                        returnStdout: true
                    ).trim()

                    if (params.ROLLBACK) {
                        if (!params.TARGET_VERSION?.trim()) {
                            error("❌ Rollback requested but TARGET_VERSION is empty")
                        }
                        env.IMAGE_TAG = params.TARGET_VERSION.trim()

                    } else if (env.TAG_TYPE == "commit") {
                        env.IMAGE_TAG = "staging-${commitId}"

                    } else if (env.TAG_TYPE == "release") {
                        def tagName = sh(
                            script: "git describe --tags --exact-match HEAD 2>/dev/null || true",
                            returnStdout: true
                        ).trim()

                        if (!tagName) {
                            error("❌ Production deployment requires Git tag push")
                        }
                        env.IMAGE_TAG = tagName
                    }

                    echo "🚀 FINAL DOCKER TAG: ${env.IMAGE_TAG}"
                }
            }
        }

        /* ---------------- BUILD & PUSH (example) ---------------- */
        stage('Docker Build & Push') {
            steps {
                script {
                    echo "🔹 Building Docker Image"

                    docker.withRegistry('', env.DOCKER_CREDENTIALS_ID) {
                        sh """
                        docker build -t ${env.IMAGE_NAME}:${env.IMAGE_TAG} .
                        docker push ${env.IMAGE_NAME}:${env.IMAGE_TAG}
                        """
                    }
                }
            }
        }
    }

    post {
        success {
            echo "✅ Pipeline completed successfully for ${env.DEPLOY_ENV}"
        }
        failure {
            echo "❌ Pipeline failed"
        }
    }
}
